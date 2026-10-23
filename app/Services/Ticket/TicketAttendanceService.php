<?php

namespace App\Services\Ticket;

use App\Models\PageCategory;
use App\Models\Ticket;
use App\Models\TicketScanner;
use App\Models\TicketSession;
use App\Models\TicketSessionAttendance;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Absensi per sesi/kelas. Hanya menerima QR ID card (bukan QR e-ticket).
 * Waktu dicek di server (jam HP petugas tidak dipercaya). Absen ganda dicegah unique index,
 * jadi 2 petugas yang scan kartu yang sama bersamaan tetap tercatat 1x.
 */
class TicketAttendanceService
{
    /** @return array{status: string, message: string, ticket?: array, scanned_at?: string} */
    public function scan(PageCategory $page, TicketScanner $scanner, ?string $sessionId, string $code, bool $confirmed): array
    {
        $session = $sessionId ? TicketSession::where('page_id', $page->getKey())->find($sessionId) : null;

        if (! $session) {
            return ['status' => 'not_found', 'message' => 'Pilih sesi atau kelasnya dulu ya.'];
        }

        $state = $session->state();

        if ($state !== TicketSession::STATE_OPEN) {
            return ['status' => 'closed', 'message' => match ($state) {
                TicketSession::STATE_UPCOMING => 'Absensi '.$session->name.' baru dibuka pukul '.$session->opensAt()->format('H:i').'. Sabar sebentar ya.',
                TicketSession::STATE_INACTIVE => 'Sesi '.$session->name.' sedang dinonaktifkan penyelenggara.',
                default => 'Absensi '.$session->name.' sudah ditutup pukul '.$session->closesAt()->format('H:i').'. Peserta sudah tidak bisa absen lagi.',
            }];
        }

        $code = trim($code);
        $byQr = (bool) preg_match('/^[A-Za-z0-9]{48}$/', $code);

        if ($byQr) {
            $ticket = Ticket::where('page_id', $page->getKey())->where('card_token', $code)->first();

            if (! $ticket) {
                return Ticket::where('page_id', $page->getKey())->where('token', $code)->exists()
                    ? ['status' => 'wrong_card', 'message' => 'Ini QR e-ticket. Untuk absen kelas, pakai QR di ID card ya. Belum punya? Cetak dulu di kiosk.']
                    : ['status' => 'not_found', 'message' => 'Kartu tidak dikenali. Kalau kartunya baru dicetak ulang, kartu lama memang sudah tidak berlaku.'];
            }
        } elseif (preg_match('/^\d{6}$/', $code)) {
            // QR rusak: petugas boleh ketik kode booking, tapi wajib cocokkan nama dulu.
            $ticket = Ticket::where('page_id', $page->getKey())->where('booking_code', $code)->first();

            if (! $ticket) {
                return ['status' => 'not_found', 'message' => 'Kode booking tidak ditemukan di acara ini.'];
            }

            if (! $ticket->hasCard()) {
                return ['status' => 'wrong_card', 'message' => $ticket->holder_name.' belum mencetak ID card. Arahkan ke kiosk dulu ya.', 'ticket' => $this->summary($ticket)];
            }
        } else {
            return ['status' => 'not_found', 'message' => 'Kode tidak dikenali. Scan QR di ID card, atau ketik kode booking 6 angka.'];
        }

        if (! $session->allowsCategory($ticket->category_id)) {
            return ['status' => 'not_allowed', 'message' => 'Tiket '.$ticket->category_name.' tidak terdaftar untuk '.$session->name.'.', 'ticket' => $this->summary($ticket)];
        }

        if ($existing = $this->existing($session, $ticket)) {
            return $this->already($session, $ticket, $existing);
        }

        if (! $byQr && ! $confirmed) {
            return ['status' => 'confirm', 'message' => 'Cocokkan nama dengan orangnya, lalu konfirmasi.', 'ticket' => $this->summary($ticket)];
        }

        try {
            $attendance = new TicketSessionAttendance();
            $attendance->forceFill([
                'page_id' => $page->getKey(),
                'session_id' => $session->getKey(),
                'ticket_id' => $ticket->getKey(),
                'scanner_id' => $scanner->getKey(),
                'scanned_at' => now(),
            ])->save();
        } catch (UniqueConstraintViolationException) {
            // Petugas lain lebih dulu menyimpan di detik yang sama.
            return $this->already($session, $ticket, $this->existing($session, $ticket));
        }

        $scanner->forceFill(['last_used_at' => now()])->save();

        return ['status' => 'ok', 'message' => 'Terima kasih, '.$ticket->holder_name.'! Selamat mengikuti '.$session->name.'.', 'ticket' => $this->summary($ticket)];
    }

    /** @return array{attended: int, eligible: int} */
    public function stats(TicketSession $session): array
    {
        return [
            'attended' => $session->attendances()->count(),
            'eligible' => $session->eligibleTickets()->count(),
        ];
    }

    private function existing(TicketSession $session, Ticket $ticket): ?TicketSessionAttendance
    {
        return TicketSessionAttendance::where('session_id', $session->getKey())->where('ticket_id', $ticket->getKey())->with('scanner:id,name')->first();
    }

    private function already(TicketSession $session, Ticket $ticket, ?TicketSessionAttendance $existing): array
    {
        $at = $existing?->scanned_at?->format('H:i');

        return [
            'status' => 'already',
            'message' => $ticket->holder_name.' sudah absen di '.$session->name.($at ? ' pukul '.$at : '').($existing?->scanner ? ' (oleh '.$existing->scanner->name.')' : '').'.',
            'ticket' => $this->summary($ticket),
        ];
    }

    private function summary(Ticket $ticket): array
    {
        return [
            'name' => $ticket->holder_name,
            'category' => $ticket->category_name,
            'booking_code' => $ticket->booking_code,
            'number' => $ticket->number,
        ];
    }
}

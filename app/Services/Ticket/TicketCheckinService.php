<?php

namespace App\Services\Ticket;

use App\Models\PageCategory;
use App\Models\Ticket;
use App\Models\TicketScanner;
use Illuminate\Support\Facades\DB;

/**
 * Registrasi ulang. QR e-ticket / QR ID card (48 karakter) -> langsung hadir.
 * Kode booking / no. tiket (diketik manual) -> petugas mencocokkan nama dulu, baru konfirmasi.
 */
class TicketCheckinService
{
    /** @return array{status: string, message: string, ticket?: array} */
    public function scan(PageCategory $page, TicketScanner $scanner, string $code, bool $confirmed): array
    {
        $code = trim($code);
        $byQr = (bool) preg_match('/^[A-Za-z0-9]{48}$/', $code);
        $column = match (true) {
            $byQr => 'token',
            (bool) preg_match('/^\d{6}$/', $code) => 'booking_code',
            (bool) preg_match('/^BB\d{8}$/i', $code) => 'number',
            default => null,
        };

        if (! $column) {
            return ['status' => 'not_found', 'message' => 'Kode tidak dikenali. Scan QR, atau ketik kode booking 6 angka.'];
        }

        return DB::transaction(function () use ($page, $scanner, $code, $column, $byQr, $confirmed) {
            // Dikunci: dua petugas yang scan tiket yang sama bersamaan, hanya satu yang berhasil.
            // QR bisa dari e-ticket atau dari ID card; dua-duanya milik tiket yang sama.
            $ticket = Ticket::where('page_id', $page->getKey())
                ->when($byQr,
                    fn ($q) => $q->where(fn ($w) => $w->where('token', $code)->orWhere('card_token', $code)),
                    fn ($q) => $q->where($column, $column === 'number' ? strtoupper($code) : $code))
                ->with('scanner:id,name')
                ->lockForUpdate()
                ->first();

            if (! $ticket) {
                return ['status' => 'not_found', 'message' => 'Tiket tidak ditemukan atau bukan untuk event ini.'];
            }

            if ($ticket->isCheckedIn()) {
                return [
                    'status' => 'already',
                    'message' => 'Tiket ini sudah discan pukul '.$ticket->checked_in_at->translatedFormat('H:i, d M').($ticket->scanner ? ' oleh '.$ticket->scanner->name : '').'.',
                    'ticket' => $this->summary($ticket),
                ];
            }

            if (! $byQr && ! $confirmed) {
                return ['status' => 'confirm', 'message' => 'Cocokkan nama dengan pemegang tiket, lalu konfirmasi.', 'ticket' => $this->summary($ticket)];
            }

            $ticket->forceFill(['checked_in_at' => now(), 'checked_in_scanner_id' => $scanner->getKey()])->save();
            $scanner->forceFill(['last_used_at' => now()])->save();

            return ['status' => 'ok', 'message' => 'Selamat datang, '.$ticket->holder_name.'!', 'ticket' => $this->summary($ticket)];
        });
    }

    /** @return array{total: int, checked_in: int, categories: array} */
    public function stats(PageCategory $page): array
    {
        $rows = Ticket::where('page_id', $page->getKey())
            ->selectRaw('category_name, COUNT(*) as total, SUM(CASE WHEN checked_in_at IS NULL THEN 0 ELSE 1 END) as checked_in')
            ->groupBy('category_name')
            ->orderBy('category_name')
            ->get();

        return [
            'total' => (int) $rows->sum('total'),
            'checked_in' => (int) $rows->sum('checked_in'),
            'categories' => $rows->map(fn ($r) => ['name' => $r->category_name, 'total' => (int) $r->total, 'checked_in' => (int) $r->checked_in])->all(),
        ];
    }

    private function summary(Ticket $ticket): array
    {
        return [
            'name' => $ticket->holder_name,
            'category' => $ticket->category_name,
            'booking_code' => $ticket->booking_code,
            'number' => $ticket->number,
            'has_card' => $ticket->hasCard(),
        ];
    }
}

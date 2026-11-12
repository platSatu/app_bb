<?php

namespace App\Services\Ticket;

use App\Models\PageCategory;
use App\Models\Ticket;
use App\Models\TicketScanner;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Registrasi ulang. QR e-ticket / QR ID card (48 karakter) -> langsung hadir.
 * Kode booking / no. tiket (diketik manual) -> petugas mencocokkan nama dulu, baru konfirmasi.
 *
 * Mode lapangan (jaringan event sering macet/putus): perangkat petugas menyimpan data tiket (fieldData) dan memutuskan
 * sendiri, lalu hasil scan dikirim menyusul ke scan() dengan jam scan aslinya ($at). Aturan & penguncian tetap di sini.
 */
class TicketCheckinService
{
    /** @return array{status: string, message: string, ticket?: array} */
    public function scan(PageCategory $page, TicketScanner $scanner, string $code, bool $confirmed, ?CarbonInterface $at = null): array
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

        return DB::transaction(function () use ($page, $scanner, $code, $column, $byQr, $confirmed, $at) {
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
                // Kiriman ulang antrean dari perangkat yang sama (balasan sebelumnya tidak sampai): sudah tercatat, bukan masalah.
                if ($at && $ticket->checked_in_scanner_id === $scanner->getKey()) {
                    return ['status' => 'ok', 'message' => 'Selamat datang, '.$ticket->holder_name.'!', 'ticket' => $this->summary($ticket)];
                }

                if ($at) {
                    Log::warning('[SCAN TIKET] Tiket dipakai dua kali saat mode lapangan', [
                        'page' => $page->getKey(), 'ticket' => $ticket->number, 'scanner' => $scanner->name, 'scanned_at' => $at->toIso8601String(), 'first_at' => $ticket->checked_in_at->toIso8601String(),
                    ]);
                }

                return [
                    'status' => 'already',
                    'message' => 'Tiket ini sudah discan pukul '.$ticket->checked_in_at->translatedFormat('H:i, d M').($ticket->scanner ? ' oleh '.$ticket->scanner->name : '').'.',
                    'ticket' => $this->summary($ticket),
                ];
            }

            if (! $byQr && ! $confirmed) {
                return ['status' => 'confirm', 'message' => 'Cocokkan nama dengan pemegang tiket, lalu konfirmasi.', 'ticket' => $this->summary($ticket)];
            }

            // Jam scan dari perangkat dipakai kalau masuk akal (tidak di masa depan, tidak lebih dari sehari lalu); selain itu jam server.
            $when = $at && $at->lessThanOrEqualTo(now()) && $at->greaterThan(now()->subDay()) ? $at : now();
            $ticket->forceFill(['checked_in_at' => $when, 'checked_in_scanner_id' => $scanner->getKey()])->save();
            $scanner->forceFill(['last_used_at' => now()])->save();

            return ['status' => 'ok', 'message' => 'Selamat datang, '.$ticket->holder_name.'!', 'ticket' => $this->summary($ticket)];
        });
    }

    /**
     * Data tiket untuk mode lapangan; $since = hanya yang berubah sejak itu (tiket baru, sudah masuk, ganti kartu).
     * Isi QR tidak dikirim: hanya hash-nya (dengan salt per event), jadi data di perangkat tidak bisa dipakai membuat QR.
     * Baris: [hash QR e-ticket, hash QR ID card, kode booking, nama, kategori, jam masuk, nama petugas, punya ID card].
     * Dibaca tanpa model (query biasa) supaya tetap ringan untuk puluhan ribu tiket; ?since memakai indeks (page_id, updated_at).
     */
    public function fieldData(PageCategory $page, ?CarbonInterface $since = null): array
    {
        $salt = self::fieldSalt($page);
        $now = now();
        $tickets = [];

        DB::table('tickets')
            ->leftJoin('ticket_scanners', 'ticket_scanners.id', '=', 'tickets.checked_in_scanner_id')
            ->where('tickets.page_id', $page->getKey())
            ->when($since, fn ($q) => $q->where('tickets.updated_at', '>=', $since->copy()->subSeconds(5)))
            ->select(['tickets.number', 'tickets.token', 'tickets.card_token', 'tickets.booking_code', 'tickets.holder_name', 'tickets.category_name', 'tickets.checked_in_at', 'tickets.card_printed_at', 'ticket_scanners.name as scanner_name'])
            ->cursor()
            ->each(function ($t) use (&$tickets, $salt) {
                $tickets[$t->number] = [
                    self::fieldHash($salt, $t->token),
                    $t->card_token ? self::fieldHash($salt, $t->card_token) : null,
                    $t->booking_code,
                    $t->holder_name,
                    $t->category_name,
                    $t->checked_in_at ? Carbon::parse($t->checked_in_at)->toIso8601String() : null,
                    $t->scanner_name,
                    $t->card_printed_at ? 1 : 0,
                ];
            });

        return ['salt' => $salt, 'server_time' => $now->toIso8601String(), 'full' => ! $since, 'tickets' => (object) $tickets];
    }

    public static function fieldSalt(PageCategory $page): string
    {
        return substr(hash_hmac('sha256', 'scan-field|'.$page->getKey(), (string) config('app.key')), 0, 16);
    }

    /** Sama persis dengan hitungan di halaman scan: SHA-256(salt + isi QR), 16 karakter pertama (64 bit, cukup untuk jutaan tiket). */
    public static function fieldHash(string $salt, string $code): string
    {
        return substr(hash('sha256', $salt.$code), 0, 16);
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

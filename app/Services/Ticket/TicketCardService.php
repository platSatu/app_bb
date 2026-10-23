<?php

namespace App\Services\Ticket;

use App\Models\PageCategory;
use App\Models\Ticket;
use App\Models\TicketCardLog;
use App\Models\TicketScanner;
use App\Services\Media\QrCodeGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * E-ticket -> ID card.
 * - Kiosk hanya menerima QR e-ticket (token 48 karakter), bukan kode yang diketik, supaya tidak ada
 *   yang mencetak kartu milik orang lain dengan menebak kode booking.
 * - ID card punya QR sendiri (card_token), berbeda dari QR e-ticket. Absensi kelas hanya menerima QR ini.
 * - Kiosk mencetak sekali saja; cetak ulang lewat admin/petugas dan selalu tercatat.
 * Semua perubahan tiket memakai lockForUpdate: 2 kiosk yang scan tiket yang sama bersamaan, hanya 1 yang mencetak.
 */
class TicketCardService
{
    public const SIZE_CARD = 'card';
    public const SIZE_A5 = 'a5';

    /** [lebar, tinggi] dalam mm untuk orientasi portrait. */
    public const SIZES = [
        self::SIZE_CARD => ['label' => 'Ukuran KTP', 'hint' => '85,6 × 54 mm · cocok untuk kartu PVC atau kertas tebal', 'mm' => [54, 85.6]],
        self::SIZE_A5 => ['label' => 'A5 untuk tali lanyard', 'hint' => '148 × 210 mm · kertas biasa, dimasukkan ke holder gantung', 'mm' => [148, 210]],
    ];

    public const ORIENTATIONS = ['portrait' => 'Tegak (portrait)', 'landscape' => 'Mendatar (landscape)'];

    public const DEFAULTS = [
        'enabled' => false,
        'size' => self::SIZE_CARD,
        'orientation' => 'portrait',
        'label' => 'PESERTA',
        'show_category' => true,
        'hole' => true,
    ];

    public function __construct(private readonly QrCodeGenerator $qr)
    {
    }

    public static function settings(PageCategory $page): array
    {
        $settings = array_replace(self::DEFAULTS, array_intersect_key((array) $page->setting('ticket.card', []), self::DEFAULTS));
        $settings['size'] = array_key_exists($settings['size'], self::SIZES) ? $settings['size'] : self::SIZE_CARD;
        $settings['orientation'] = array_key_exists($settings['orientation'], self::ORIENTATIONS) ? $settings['orientation'] : 'portrait';

        return $settings;
    }

    public static function enabled(PageCategory $page): bool
    {
        return (bool) self::settings($page)['enabled'];
    }

    /** Ukuran kertas [lebar, tinggi] mm sesuai pengaturan. */
    public static function paper(array $settings): array
    {
        [$w, $h] = self::SIZES[$settings['size']]['mm'];

        return $settings['orientation'] === 'landscape' ? [$h, $w] : [$w, $h];
    }

    /**
     * Kiosk langkah 1: cari pemilik e-ticket, TANPA mengubah apa pun.
     *
     * @return array{status: string, message: string, ticket?: Ticket}
     */
    public function lookup(PageCategory $page, string $code): array
    {
        if (! self::enabled($page)) {
            return ['status' => 'disabled', 'message' => 'Cetak ID card belum dibuka oleh penyelenggara. Silakan tanya petugas ya.'];
        }

        $code = trim($code);

        if (! preg_match('/^[A-Za-z0-9]{48}$/', $code)) {
            return ['status' => 'not_found', 'message' => 'QR-nya belum terbaca. Coba arahkan QR di e-ticket kamu ke scanner sekali lagi ya.'];
        }

        $ticket = Ticket::where('page_id', $page->getKey())->where('token', $code)->first();

        if (! $ticket) {
            return Ticket::where('page_id', $page->getKey())->where('card_token', $code)->exists()
                ? ['status' => 'wrong_card', 'message' => 'Ini QR dari ID card, bukan e-ticket. Kamu sudah punya ID card, jadi tidak perlu cetak lagi.']
                : ['status' => 'not_found', 'message' => 'E-ticket ini tidak kami temukan untuk acara ini. Pastikan kamu membuka e-ticket yang benar ya.'];
        }

        if ($ticket->hasCard()) {
            return ['status' => 'already', 'message' => $this->alreadyMessage($ticket), 'ticket' => $ticket];
        }

        return ['status' => 'ready', 'message' => 'Halo, '.$ticket->holder_name.'! Pastikan nama di kartu sudah benar, lalu tekan Cetak.', 'ticket' => $ticket];
    }

    /**
     * Kiosk langkah 2: tukar e-ticket jadi ID card (sekali saja) + otomatis registrasi ulang.
     *
     * @return array{status: string, message: string, ticket?: Ticket}
     */
    public function redeem(PageCategory $page, TicketScanner $kiosk, string $code): array
    {
        $check = $this->lookup($page, $code);

        if ($check['status'] !== 'ready') {
            return $check;
        }

        return DB::transaction(function () use ($page, $kiosk, $code) {
            // Dikunci: kiosk lain yang scan e-ticket yang sama di detik yang sama harus menunggu, lalu ditolak.
            $ticket = Ticket::where('page_id', $page->getKey())->where('token', trim($code))->lockForUpdate()->firstOrFail();

            if ($ticket->hasCard()) {
                return ['status' => 'already', 'message' => $this->alreadyMessage($ticket), 'ticket' => $ticket];
            }

            $this->apply($ticket, TicketCardLog::ACTION_PRINT, TicketCardLog::SOURCE_KIOSK, $kiosk, null, checkIn: true);
            $kiosk->forceFill(['last_used_at' => now()])->save();

            return ['status' => 'ok', 'message' => 'ID card sedang dicetak. Silakan ambil di printer ya, '.$ticket->holder_name.'!', 'ticket' => $ticket];
        });
    }

    /**
     * Admin / petugas: cetak pertama atau cetak ulang.
     * $replace = kartu lama hilang -> QR baru dibuat, kartu lama langsung tidak bisa dipakai absen.
     * $checkIn = petugas mencetak di lokasi, berarti orangnya hadir.
     */
    public function issue(Ticket $ticket, string $source, ?TicketScanner $scanner = null, ?string $actorId = null, bool $replace = false, bool $checkIn = false): Ticket
    {
        return DB::transaction(function () use ($ticket, $source, $scanner, $actorId, $replace, $checkIn) {
            $locked = Ticket::whereKey($ticket->getKey())->lockForUpdate()->firstOrFail();
            $action = match (true) {
                ! $locked->hasCard() => TicketCardLog::ACTION_PRINT,
                $replace => TicketCardLog::ACTION_REPLACE,
                default => TicketCardLog::ACTION_REPRINT,
            };

            $this->apply($locked, $action, $source, $scanner, $actorId, $checkIn);
            $scanner?->forceFill(['last_used_at' => now()])->save();

            return $locked;
        });
    }

    /** Dokumen HTML siap cetak (ukuran kertas sudah diatur lewat @page). */
    public function render(PageCategory $page, Ticket $ticket, bool $autoPrint = false): string
    {
        $settings = self::settings($page);

        return view('page.public.id-card-print', [
            'card' => $this->cardData($page, $settings, $ticket),
            'settings' => $settings,
            'paper' => self::paper($settings),
            'autoPrint' => $autoPrint,
            'title' => 'ID Card '.$ticket->number,
        ])->render();
    }

    /** Data untuk partial kartu. $ticket null = contoh untuk preview pengaturan. */
    public function cardData(PageCategory $page, array $settings, ?Ticket $ticket): array
    {
        $page->loadMissing('branch.company');

        return [
            'settings' => $settings,
            'color' => $page->setting('ticket.color') ?: '#e36666',
            'logo' => $page->branch?->company?->logoUrl(),
            'company' => $page->branch?->company?->name,
            'eventName' => $page->name,
            'eventDate' => $page->start_date?->translatedFormat('d M Y'),
            'holder' => $ticket?->holder_name ?? 'Nama Peserta',
            'category' => $ticket?->category_name ?? 'VIP',
            'number' => $ticket?->number ?? 'BB12345678',
            'booking' => $ticket?->booking_code ?? '123456',
            'qr' => $ticket?->card_token ? $this->qr->dataUri($ticket->card_token, 10) : null,
        ];
    }

    private function apply(Ticket $ticket, string $action, string $source, ?TicketScanner $scanner, ?string $actorId, bool $checkIn): void
    {
        $changes = [
            'card_printed_at' => $ticket->card_printed_at ?? now(),
            'card_print_count' => $ticket->card_print_count + 1,
        ];

        if (! $ticket->card_token || $action === TicketCardLog::ACTION_REPLACE) {
            $changes['card_token'] = $this->newToken();
        }

        if ($checkIn && ! $ticket->isCheckedIn()) {
            $changes['checked_in_at'] = now();
            $changes['checked_in_scanner_id'] = $scanner?->getKey();
            $changes['checked_in_by'] = $actorId;
        }

        $ticket->forceFill($changes)->save();

        (new TicketCardLog())->forceFill([
            'page_id' => $ticket->page_id,
            'ticket_id' => $ticket->getKey(),
            'action' => $action,
            'source' => $source,
            'scanner_id' => $scanner?->getKey(),
            'actor_id' => $actorId,
        ])->save();
    }

    private function newToken(): string
    {
        do {
            $token = Str::random(48);
        } while (Ticket::where('card_token', $token)->orWhere('token', $token)->exists());

        return $token;
    }

    private function alreadyMessage(Ticket $ticket): string
    {
        return 'ID card untuk tiket ini sudah dicetak pada '.$ticket->card_printed_at->translatedFormat('d M, H:i')
            .'. Kalau kartunya hilang atau rusak, minta bantuan petugas untuk cetak ulang ya.';
    }
}

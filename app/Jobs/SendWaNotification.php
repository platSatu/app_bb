<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Models\ScheduleSession;
use App\Models\ScheduleStudent;
use App\Models\TicketOrder;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\WaDevice;
use App\Services\WhatsApp\WaGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Notifikasi WhatsApp otomatis (isi sama dengan email) lewat nomor WhatsApp branch.
 * Hanya dikirim kalau sakelar jenisnya nyala di Settings › Branch, paket WhatsApp aktif & ada nomor terhubung.
 * Dikirim di antrean supaya pembayaran/penyimpanan tidak ikut menunggu; gagal kirim tidak mengganggu proses utama.
 */
class SendWaNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 60;

    public function __construct(public string $branchId, public string $phone, public string $text, public string $kind)
    {
        $this->afterCommit();
    }

    /** Antrekan pesan; false kalau notifikasi jenis ini tidak aktif atau nomor tujuan kosong. */
    public static function queue(?string $branchId, string $kind, ?string $phone, string $text): bool
    {
        $phone = User::normalizePhone($phone);

        if (! $branchId || $phone === '' || ! UserBranch::find($branchId)?->waNotifies($kind) || ! WaDevice::connectedByBranch($branchId)) {
            return false;
        }

        dispatch(new self($branchId, $phone, $text, $kind));

        return true;
    }

    /** $kind: issued | reminder | paid, sama dengan email invoice. */
    public static function invoice(Invoice $invoice, string $kind): bool
    {
        $rp = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.');
        $company = $invoice->company?->name ?? config('app.name');

        $text = $kind === 'paid'
            ? "Halo {$invoice->customer_name}, terima kasih! Pembayaran invoice {$invoice->number} sudah kami terima.\n\n"
                ."{$invoice->description}\nTotal dibayar: {$rp($invoice->paid_amount)}\n"
                .'Dibayar pada '.$invoice->paid_at?->translatedFormat('d F Y H:i').".\n\nSimpan pesan ini sebagai bukti pembayaran.\n\nSalam,\n{$company}"
            : "Halo {$invoice->customer_name}, ".($kind === 'reminder' ? 'ini pengingat ramah untuk tagihan yang belum dibayar:' : "ada tagihan baru dari {$company}:")."\n\n"
                ."Nomor: {$invoice->number}\n{$invoice->description}: {$rp($invoice->amount)}\n"
                .($invoice->due_date ? 'Jatuh tempo: '.$invoice->due_date->translatedFormat('d F Y')."\n" : '')
                ."\nBayar lewat link ini (virtual account, QRIS, atau e-wallet):\n{$invoice->payUrl()}\n\n"
                ."Abaikan pesan ini kalau sudah membayar.\n\nSalam,\n{$company}";

        return self::queue($invoice->branch_id, 'invoice', $invoice->customer_phone, $text);
    }

    public static function tickets(TicketOrder $order): bool
    {
        $order->loadMissing('page.branch.company');

        $text = "Halo {$order->name}, terima kasih! Pembayaran untuk {$order->page->name} sudah kami terima.\n\n"
            ."Nomor pesanan: {$order->reference}\nTotal: Rp ".number_format((int) $order->total, 0, ',', '.')."\n\n"
            ."Lihat semua e-ticket di sini, lalu tunjukkan QR-nya saat registrasi ulang:\n{$order->url()}\n\n"
            ."Jangan bagikan link ini ke orang yang tidak berkepentingan ya, karena siapa pun yang memegang link bisa memakai tiketnya.\n\n"
            .'Salam,'."\n".($order->page->branch?->company?->name ?? config('app.name'));

        return self::queue($order->page->branch_id, 'ticket', $order->phone, $text);
    }

    /** Pengingat les H-1 untuk satu murid. Relasi grade, teacher, room & branch sesi sebaiknya sudah dimuat. */
    public static function lesson(ScheduleSession $session, ScheduleStudent $student): bool
    {
        $text = 'Halo '.($student->guardian_name ?: $student->name).", pengingat les {$student->name} besok ya:\n\n"
            .$session->grade?->label()."\n"
            .$session->start_at->translatedFormat('l, d F Y').' · '.$session->timeRange()."\n"
            .'Pengajar: '.($session->teacher?->name ?? '-').' · Ruang: '.($session->room?->name ?? '-')."\n\n"
            ."Kartu absen QR (tunjukkan ke pengajar saat les dimulai):\n{$student->cardUrl()}\n\n"
            .'Kalau berhalangan, kabari kami ya. Sampai jumpa!'."\n\n".($session->branch?->name ?? config('app.name'));

        return self::queue($session->branch_id, 'schedule', $student->phone, $text);
    }

    public function handle(WaGateway $wa): void
    {
        // Dicek ulang saat dikirim: sakelar bisa dimatikan / nomor terputus selama pesan menunggu di antrean.
        $device = WaDevice::connectedByBranch($this->branchId)[$this->branchId] ?? null;

        if (! $device || ! UserBranch::find($this->branchId)?->waNotifies($this->kind)) {
            return;
        }

        $result = $wa->sendText($this->branchId, ['device_id' => $device, 'to' => $this->phone, 'text' => $this->text, 'client_ref' => (string) Str::uuid()]);

        if (! $result['success']) {
            Log::warning('[WA NOTIF] Gagal kirim', ['branch' => $this->branchId, 'kind' => $this->kind, 'status' => $result['status'] ?? null, 'message' => $result['message'] ?? null]);
        }
    }
}

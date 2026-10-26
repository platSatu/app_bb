<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use App\Models\UserBranch;
use App\Models\WaFormIntegration;
use App\Models\WaFormSubmission;
use App\Services\Invoice\InvoiceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Jawaban form (Google Form) -> pendaftar + pesan WhatsApp ke pengisinya.
 * Pembayaran aktif: pesan berisi link bayar, QR registrasi ulang dikirim setelah lunas.
 * Registrasi ulang tanpa pembayaran: QR langsung ada di pesan pertama.
 * Aman dikirim ulang: jawaban yang sama dalam 10 menit tidak diproses dua kali, go_bizbos menolak client_ref yang sama.
 */
class FormRelay
{
    public const MAX_FIELDS = 100;

    private const PER_MINUTE = 30;

    public function __construct(private WaGateway $wa, private InvoiceService $invoices) {}

    /** @return array{status: string, message: string} status: sent | failed | duplicate | limited */
    public function handle(WaFormIntegration $integration, array $raw): array
    {
        $answers = $this->answers($raw);
        $fingerprint = md5($integration->id.json_encode($answers));

        if (! Cache::add('wa-form:'.$fingerprint, true, now()->addMinutes(10))) {
            return ['status' => 'duplicate', 'message' => 'Jawaban yang sama sudah diproses, tidak dikirim ulang.'];
        }

        $limiter = 'wa-form:'.$integration->id;
        if (RateLimiter::tooManyAttempts($limiter, self::PER_MINUTE)) {
            Cache::forget('wa-form:'.$fingerprint);

            return ['status' => 'limited', 'message' => 'Terlalu banyak kiriman dalam semenit. Coba lagi sebentar.'];
        }
        RateLimiter::hit($limiter, 60);

        $phone = $this->phone($answers, $integration->phone_field);
        $name = $integration->name_field ? mb_substr((string) $this->answer($answers, $integration->name_field), 0, 150) ?: null : null;

        $submission = DB::transaction(function () use ($integration, $answers, $phone, $name) {
            $submission = new WaFormSubmission();
            $submission->forceFill([
                'integration_id' => $integration->id,
                'payload' => $answers,
                'name' => $name,
                'phone' => $phone,
                'code' => $phone && $integration->registration_enabled ? WaFormSubmission::newCode() : null,
                'status' => 'failed',
            ]);

            if ($phone && $integration->payment_enabled && $integration->amount) {
                $submission->invoice_id = $this->invoices->createForForm(
                    UserBranch::findOrFail($integration->branch_id), 'Pendaftaran '.$integration->name, $name ?? '+'.$phone, $phone, $integration->amount
                )->getKey();
            }
            $submission->save();

            return $submission;
        });

        $result = $phone === null
            ? ['success' => false, 'message' => "Kolom \"{$integration->phone_field}\" tidak ditemukan atau nomornya belum benar. Samakan dengan judul pertanyaan di Google Form."]
            : $this->send($integration, $submission, $this->compose($integration, $submission, $answers), 'gform:'.$fingerprint);

        $submission->forceFill([
            'status' => $result['success'] ? 'sent' : 'failed',
            'error' => $result['success'] ? null : mb_substr((string) $result['message'], 0, 500),
            'qr_sent_at' => $result['success'] && $submission->code && ! $submission->invoice_id ? now() : null,
        ])->save();

        if ($result['success']) {
            $integration->newQuery()->whereKey($integration->id)->incrementEach(['trigger_count' => 1], ['last_triggered_at' => now()]);
        }

        return $result['success']
            ? ['status' => 'sent', 'message' => 'Pesan WhatsApp masuk antrean kirim.']
            : ['status' => 'failed', 'message' => (string) $result['message']];
    }

    /** Tagihan pendaftaran lunas: kirim QR registrasi ulang atau ucapan terima kasih. */
    public function paid(string $invoiceId): void
    {
        if ($submission = WaFormSubmission::with('integration')->where('invoice_id', $invoiceId)->first()) {
            $this->sendTicket($submission);
        }
    }

    /** Kirim QR registrasi ulang (dipakai saat lunas & tombol kirim ulang di laporan). Hanya terkirim sekali per klaim. */
    public function sendTicket(WaFormSubmission $submission): bool
    {
        // Klaim dulu supaya tidak terkirim dua kali walau callback/klik datang bersamaan.
        if (! $submission->phone || ! $submission->integration || ! WaFormSubmission::whereKey($submission->id)->whereNull('qr_sent_at')->update(['qr_sent_at' => now()])) {
            return false;
        }

        $name = $submission->integration->name;
        $text = $submission->invoice_id ? "Pembayaran *{$name}* sudah kami terima, terima kasih!" : "Ini QR pendaftaran *{$name}* Anda.";
        if ($submission->code) {
            $text .= "\n\nTunjukkan QR ini saat registrasi ulang:\n".$submission->ticketUrl()."\nKode: *{$submission->code}*";
        }

        $result = $this->send($submission->integration, $submission, $text, 'gform-qr:'.$submission->id.':'.now()->timestamp);
        if (! $result['success']) {
            $submission->forceFill(['qr_sent_at' => null, 'error' => mb_substr('Pesan QR: '.$result['message'], 0, 500)])->save();
        }

        return $result['success'];
    }

    /**
     * Ganti {{Judul Pertanyaan}} dengan jawabannya (huruf besar/kecil, spasi & tanda baca di ujung diabaikan);
     * pertanyaan yang tidak ada jawabannya dibiarkan. Kata sistem ($system: link_bayar, link_qr, kode) selalu
     * diganti supaya tulisan {{...}}-nya tidak ikut terkirim; kalau fiturnya tidak aktif, dipakai jawaban form
     * berjudul sama (kalau ada) atau dikosongkan.
     */
    public function render(string $message, array $answers, array $system = []): string
    {
        $lookup = collect($answers)->mapWithKeys(fn ($v, $k) => [$this->key($k) => $v])->all();

        return preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/u', function ($m) use ($lookup, $system) {
            $key = $this->key($m[1]);

            if (array_key_exists($key, $system)) {
                return (string) ($system[$key] ?? $lookup[$key] ?? '');
            }

            return filled($lookup[$key] ?? null) ? $lookup[$key] : $m[0];
        }, $message);
    }

    /**
     * Pesan pertama: jawaban form + link bayar/QR. Link yang tidak ditulis owner ditambahkan otomatis di bawah pesan.
     * {{link_qr}} yang ditulis owner selalu berisi link tiket; selama belum lunas, halaman tiket menampilkan tombol bayar
     * (QR baru muncul setelah lunas), jadi aman dikirim sejak pesan pertama.
     */
    private function compose(WaFormIntegration $integration, WaFormSubmission $submission, array $answers): string
    {
        $payUrl = $submission->invoice_id ? $submission->invoice->payUrl() : null;
        $ticketUrl = $submission->ticketUrl();
        $text = $this->render($integration->message, $answers, ['link_bayar' => $payUrl, 'link_qr' => $ticketUrl, 'kode' => $submission->code]);

        if ($payUrl && ! str_contains($text, $payUrl)) {
            $text .= "\n\nSelesaikan pembayaran Rp ".number_format($integration->amount, 0, ',', '.')." di sini:\n".$payUrl;
            if ($submission->code) {
                $text .= "\nQR registrasi ulang dikirim setelah pembayaran diterima.";
            }
        }
        if ($ticketUrl && ! $payUrl && ! str_contains($text, $ticketUrl)) {
            $text .= "\n\nTunjukkan QR ini saat registrasi ulang:\n".$ticketUrl;
        }

        return $text;
    }

    private function send(WaFormIntegration $integration, WaFormSubmission $submission, string $text, string $ref): array
    {
        return $this->wa->sendText($integration->branch_id, ['device_id' => $integration->device_id, 'to' => $submission->phone, 'text' => $text, 'client_ref' => $ref]);
    }

    /** Jawaban dirapikan: maksimal 100 kolom, pilihan ganda digabung koma, teks dipotong 2000 karakter. */
    private function answers(array $raw): array
    {
        return collect($raw)->take(self::MAX_FIELDS)
            ->mapWithKeys(fn ($v, $k) => [mb_substr(trim((string) $k), 0, 191) => mb_substr(is_array($v) ? implode(', ', array_map(fn ($x) => is_scalar($x) ? (string) $x : '', $v)) : (is_scalar($v) ? (string) $v : ''), 0, 2000)])
            ->all();
    }

    private function answer(array $answers, string $field): ?string
    {
        return collect($answers)->first(fn ($v, $k) => $this->key($k) === $this->key($field));
    }

    private function phone(array $answers, string $field): ?string
    {
        $phone = User::normalizePhone($this->answer($answers, $field));

        return strlen($phone) >= 10 ? $phone : null;
    }

    /** Kunci pencocokan judul: huruf kecil, spasi tak terlihat (hasil salin-tempel) dirapikan, tanda baca di ujung (":", "?", "*") diabaikan. */
    private function key(string $text): string
    {
        $text = preg_replace('/[\s\x{00A0}\x{200B}-\x{200D}\x{FEFF}]+/u', ' ', $text);

        return mb_strtolower(trim($text, " \t:?*.-"));
    }
}

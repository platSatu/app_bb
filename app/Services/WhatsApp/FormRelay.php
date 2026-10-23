<?php

namespace App\Services\WhatsApp;

use App\Models\User;
use App\Models\WaFormIntegration;
use App\Models\WaFormSubmission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Jawaban form (Google Form) -> pesan WhatsApp ke pengisinya.
 * Aman dikirim ulang: jawaban yang sama persis dalam 10 menit tidak dikirim dua kali,
 * dan go_bizbos menolak client_ref yang sama.
 */
class FormRelay
{
    public const MAX_FIELDS = 100;

    private const PER_MINUTE = 30;

    public function __construct(private WaGateway $wa) {}

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
        $result = $phone === null
            ? ['success' => false, 'message' => "Kolom \"{$integration->phone_field}\" tidak ditemukan atau nomornya belum benar. Samakan dengan judul pertanyaan di Google Form."]
            : $this->wa->sendText($integration->branch_id, [
                'device_id' => $integration->device_id,
                'to' => $phone,
                'text' => $this->render($integration->message, $answers),
                'client_ref' => 'gform:'.$fingerprint,
            ]);

        $submission = new WaFormSubmission();
        $submission->forceFill([
            'integration_id' => $integration->id,
            'payload' => $answers,
            'phone' => $phone,
            'status' => $result['success'] ? 'sent' : 'failed',
            'error' => $result['success'] ? null : mb_substr((string) $result['message'], 0, 500),
        ])->save();

        if ($result['success']) {
            $integration->newQuery()->whereKey($integration->id)->incrementEach(['trigger_count' => 1], ['last_triggered_at' => now()]);
        }

        return $result['success']
            ? ['status' => 'sent', 'message' => 'Pesan WhatsApp masuk antrean kirim.']
            : ['status' => 'failed', 'message' => (string) $result['message']];
    }

    /** Ganti {{Judul Pertanyaan}} dengan jawabannya (huruf besar/kecil & spasi diabaikan). Yang tidak ada jawabannya dibiarkan. */
    public function render(string $message, array $answers): string
    {
        $lookup = collect($answers)->mapWithKeys(fn ($v, $k) => [$this->key($k) => $v])->all();

        return preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/u', fn ($m) => filled($lookup[$this->key($m[1])] ?? null) ? $lookup[$this->key($m[1])] : $m[0], $message);
    }

    /** Jawaban dirapikan: maksimal 100 kolom, pilihan ganda digabung koma, teks dipotong 2000 karakter. */
    private function answers(array $raw): array
    {
        return collect($raw)->take(self::MAX_FIELDS)
            ->mapWithKeys(fn ($v, $k) => [mb_substr(trim((string) $k), 0, 191) => mb_substr(is_array($v) ? implode(', ', array_map(fn ($x) => is_scalar($x) ? (string) $x : '', $v)) : (is_scalar($v) ? (string) $v : ''), 0, 2000)])
            ->all();
    }

    private function phone(array $answers, string $field): ?string
    {
        $value = collect($answers)->first(fn ($v, $k) => $this->key($k) === $this->key($field));
        $phone = User::normalizePhone($value);

        return strlen($phone) >= 10 ? $phone : null;
    }

    private function key(string $text): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($text)));
    }
}

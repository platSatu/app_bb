<?php

namespace App\Services\Payment;

use App\Models\DisbursementSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Duitku DISBURSEMENT (Transfer Online): produk Duitku yang MENGIRIM uang ke rekening.
 * Kredensial & tanda tangan berbeda dari payment gateway (SHA256 biasa, bukan HMAC).
 * Urutan string tanda tangan disalin dari dokumen Duitku / teleios yang sudah berjalan, jangan diubah.
 * Alur wajib: inquiry() dulu -> transfer() dengan disburseId hasil inquiry.
 */
class DuitkuDisbursementClient
{
    public function __construct(private readonly DisbursementSetting $setting)
    {
    }

    public static function make(): self
    {
        $setting = DisbursementSetting::current();

        if (! $setting->isConfigured()) {
            throw new RuntimeException('Pencairan dana belum dikonfigurasi admin. Silakan hubungi admin kami.');
        }

        return new self($setting);
    }

    public function isSandbox(): bool
    {
        return $this->setting->isSandbox();
    }

    /** @return array{disburseId: ?string, accountName: ?string, responseCode: ?string, responseDesc: ?string, raw: array} */
    public function inquiry(string $bankCode, string $bankAccount, int $amount, string $purpose): array
    {
        $ts = $this->timestamp();
        $decoded = $this->post($this->isSandbox() ? '/inquirysandbox' : '/inquiry', [
            ...$this->auth(),
            'bankCode' => $bankCode,
            'bankAccount' => $bankAccount,
            'amountTransfer' => $amount,
            'purpose' => $purpose,
            'timestamp' => $ts,
            'signature' => hash('sha256', $this->email().$ts.$bankCode.$bankAccount.$amount.$purpose.$this->secret()),
        ]);

        return [
            'disburseId' => isset($decoded['disburseId']) ? (string) $decoded['disburseId'] : null,
            'accountName' => $decoded['accountName'] ?? null,
            'responseCode' => $decoded['responseCode'] ?? null,
            'responseDesc' => $decoded['responseDesc'] ?? null,
            'raw' => $decoded,
        ];
    }

    /** Ini yang benar-benar memindahkan uang. */
    public function transfer(string $disburseId, string $bankCode, string $bankAccount, int $amount, string $accountName, string $purpose, string $custRef): array
    {
        $ts = $this->timestamp();

        return $this->post($this->isSandbox() ? '/transfersandbox' : '/transfer', [
            'disburseId' => $disburseId,
            ...$this->auth(),
            'bankCode' => $bankCode,
            'bankAccount' => $bankAccount,
            'amountTransfer' => $amount,
            'accountName' => $accountName,
            'custRefNumber' => $custRef,
            'purpose' => $purpose,
            'timestamp' => $ts,
            'signature' => hash('sha256', $this->email().$ts.$bankCode.$bankAccount.$accountName.$custRef.$amount.$purpose.$disburseId.$this->secret()),
        ]);
    }

    public function inquiryStatus(string $disburseId): array
    {
        $ts = $this->timestamp();

        return $this->post('/inquirystatus', [
            'disburseId' => $disburseId,
            ...$this->auth(),
            'timestamp' => $ts,
            'signature' => hash('sha256', $this->email().$ts.$disburseId.$this->secret()),
        ]);
    }

    public function checkBalance(): array
    {
        $ts = $this->timestamp();

        return $this->post('/checkbalance', [...$this->auth(), 'timestamp' => $ts, 'signature' => hash('sha256', $this->email().$ts.$this->secret())]);
    }

    /** Daftar bank dari Duitku, disimpan 1 hari. @return array<int, array{bankCode: string, bankName: string}> */
    public function banks(): array
    {
        return Cache::remember('duitku-disbursement-banks:'.($this->isSandbox() ? 's' : 'p'), now()->addDay(), function () {
            $ts = $this->timestamp();
            $decoded = $this->post('/listBank', [...$this->auth(), 'timestamp' => $ts, 'signature' => hash('sha256', $this->email().$ts.$this->secret())]);

            if (($decoded['responseCode'] ?? null) !== '00' || empty($decoded['Banks'])) {
                throw new RuntimeException('Daftar bank belum bisa diambil dari Duitku ('.($decoded['responseDesc'] ?? 'respons kosong').').');
            }

            return collect($decoded['Banks'])->map(fn ($b) => ['bankCode' => (string) $b['bankCode'], 'bankName' => (string) $b['bankName']])->sortBy('bankName')->values()->all();
        });
    }

    /** 9 digit: detik + 3 digit acak, supaya dua transfer di detik yang sama tetap beda. */
    public function custRefNumber(): string
    {
        return str_pad((string) (time() % 1000000), 6, '0', STR_PAD_LEFT).str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
    }

    private function post(string $path, array $payload): array
    {
        $base = $this->isSandbox() ? 'https://sandbox.duitku.com/webapi/api/disbursement' : 'https://passport.duitku.com/webapi/api/disbursement';
        $response = Http::asJson()->acceptJson()->timeout(30)->post($base.$path, $payload);
        $decoded = $response->json() ?? [];

        if ($response->failed() && empty($decoded)) {
            throw new RuntimeException('Duitku Disbursement HTTP '.$response->status());
        }

        return $decoded;
    }

    private function auth(): array
    {
        return ['userId' => (int) $this->setting->credential('user_id'), 'email' => $this->email()];
    }

    private function email(): string
    {
        return (string) $this->setting->credential('email');
    }

    private function secret(): string
    {
        return (string) $this->setting->credential('secret_key');
    }

    private function timestamp(): int
    {
        return (int) round(microtime(true) * 1000);
    }
}

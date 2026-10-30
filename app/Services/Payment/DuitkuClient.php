<?php

namespace App\Services\Payment;

use App\Models\PaymentGatewaySetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Pemanggilan API Duitku (pola tanda tangan sama dengan teleios yang sudah berjalan). */
class DuitkuClient
{
    public function __construct(private readonly PaymentGatewaySetting $setting)
    {
    }

    /** $forCallback: callback tetap diproses walau top up baru sedang dinonaktifkan. */
    public static function make(bool $forCallback = false): self
    {
        $setting = PaymentGatewaySetting::duitku();
        $usable = $forCallback ? filled($setting->merchantCode()) && filled($setting->apiKey()) : $setting->isReady();

        if (! $usable) {
            throw new RuntimeException('Pembayaran online belum aktif. Silakan hubungi admin.');
        }

        return new self($setting);
    }

    /**
     * Buat invoice Duitku untuk apa saja (top up, tiket, ...).
     *
     * @param  array{amount: int, orderId: string, product: string, customer: array{name: string, email: string, phone: ?string}, items: array, returnUrl: string, userInfo?: string}  $invoice
     * @return array{paymentUrl: ?string, reference: ?string, message: ?string}
     */
    public function createInvoice(array $invoice, int $timeout = 20): array
    {
        // Batas panjang field Duitku (lebih dari ini order ditolak dan halaman bayar tidak muncul):
        // customerVaName 20, nama item 50, productDetails/email/merchantUserInfo 255, phoneNumber 50.
        $customer = $invoice['customer'];
        $name = trim(preg_replace('/\s+/', ' ', (string) ($customer['name'] ?: 'Customer')));
        [$firstName, $lastName] = array_pad(explode(' ', $name, 2), 2, '');
        $phone = Str::limit(preg_replace('/[^0-9+]/', '', (string) ($customer['phone'] ?? '')), 50, '');
        $email = Str::limit((string) $customer['email'], 255, '');

        $payload = [
            'paymentAmount' => $invoice['amount'],
            'merchantOrderId' => $invoice['orderId'],
            'productDetails' => Str::limit($invoice['product'], 255, ''),
            'merchantUserInfo' => Str::limit($invoice['userInfo'] ?? $email, 255, ''),
            'customerVaName' => Str::limit($name, 20, ''),
            'email' => $email,
            'phoneNumber' => $phone,
            'itemDetails' => array_map(fn (array $item) => [...$item, 'name' => Str::limit(trim((string) $item['name']) ?: 'Tagihan', 50, '')], $invoice['items']),
            'customerDetail' => ['firstName' => Str::limit($firstName, 50, ''), 'lastName' => Str::limit($lastName, 50, ''), 'email' => $email, 'phoneNumber' => $phone],
            'callbackUrl' => route('payment.duitku.callback'),
            'returnUrl' => $invoice['returnUrl'],
            'expiryPeriod' => $this->setting->expiry_minutes,
        ];

        $timestamp = (string) round(microtime(true) * 1000);

        $response = Http::connectTimeout(min(5, $timeout))->timeout($timeout)->withHeaders([
            'x-duitku-signature' => hash_hmac('sha256', $this->setting->merchantCode().$timestamp, $this->setting->apiKey()),
            'x-duitku-timestamp' => $timestamp,
            'x-duitku-merchantcode' => $this->setting->merchantCode(),
        ])->post($this->baseUrl().'/api/merchant/createinvoice', $payload);

        $data = $response->json() ?? [];

        if (empty($data['paymentUrl'])) {
            // Alasan penolakan dicatat supaya bisa dicek di storage/logs/laravel.log.
            Log::warning('Duitku createInvoice gagal', ['order' => $invoice['orderId'], 'http' => $response->status(), 'body' => Str::limit($response->body(), 500)]);
        }

        return [
            'paymentUrl' => $data['paymentUrl'] ?? null,
            'reference' => $data['reference'] ?? null,
            'message' => $data['statusMessage'] ?? ($response->failed() ? 'HTTP '.$response->status() : null),
        ];
    }

    public function verifySignature(array $callback): bool
    {
        foreach (['signature', 'merchantCode', 'amount', 'merchantOrderId'] as $field) {
            if (! isset($callback[$field])) {
                return false;
            }
        }

        // Callback untuk merchant lain (mis. kode sandbox saat mode production) langsung ditolak.
        if (! hash_equals((string) $this->setting->merchantCode(), (string) $callback['merchantCode'])) {
            return false;
        }

        $expected = hash_hmac('sha256', $callback['merchantCode'].$callback['amount'].$callback['merchantOrderId'], $this->setting->apiKey());

        return hash_equals($expected, (string) $callback['signature']);
    }

    /** Cek ulang langsung ke Duitku. Null = benar-benar lunas; selain itu alasan penolakan. */
    public function unpaidReason(string $merchantOrderId, int $amount): ?string
    {
        try {
            $response = Http::timeout(15)->post($this->baseUrl().'/api/merchant/transactionStatus', [
                'merchantCode' => $this->setting->merchantCode(),
                'merchantOrderId' => $merchantOrderId,
                'signature' => md5($this->setting->merchantCode().$merchantOrderId.$this->setting->apiKey()),
            ]);
        } catch (Throwable) {
            return 'Duitku belum bisa dihubungi untuk konfirmasi.';
        }

        $data = $response->json() ?? [];

        if (($data['statusCode'] ?? null) !== '00') {
            return 'Status di Duitku belum lunas ('.($data['statusCode'] ?? 'HTTP '.$response->status()).').';
        }

        if ((int) round((float) ($data['amount'] ?? 0)) !== $amount) {
            return 'Nominal di Duitku tidak sama dengan deposit.';
        }

        return null;
    }

    private function baseUrl(): string
    {
        return $this->setting->isSandbox() ? 'https://api-sandbox.duitku.com' : 'https://api-prod.duitku.com';
    }
}

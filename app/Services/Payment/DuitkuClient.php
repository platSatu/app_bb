<?php

namespace App\Services\Payment;

use App\Models\PaymentGatewaySetting;
use Illuminate\Support\Facades\Http;
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
    public function createInvoice(array $invoice): array
    {
        $customer = $invoice['customer'];
        [$firstName, $lastName] = array_pad(explode(' ', trim($customer['name'] ?: 'Customer'), 2), 2, '');
        $phone = (string) ($customer['phone'] ?? '');

        $payload = [
            'paymentAmount' => $invoice['amount'],
            'merchantOrderId' => $invoice['orderId'],
            'productDetails' => $invoice['product'],
            'merchantUserInfo' => $invoice['userInfo'] ?? $customer['email'],
            'customerVaName' => $customer['name'],
            'email' => $customer['email'],
            'phoneNumber' => $phone,
            'itemDetails' => $invoice['items'],
            'customerDetail' => ['firstName' => $firstName, 'lastName' => $lastName, 'email' => $customer['email'], 'phoneNumber' => $phone],
            'callbackUrl' => route('payment.duitku.callback'),
            'returnUrl' => $invoice['returnUrl'],
            'expiryPeriod' => $this->setting->expiry_minutes,
        ];

        $timestamp = (string) round(microtime(true) * 1000);

        $response = Http::timeout(20)->withHeaders([
            'x-duitku-signature' => hash_hmac('sha256', $this->setting->merchantCode().$timestamp, $this->setting->apiKey()),
            'x-duitku-timestamp' => $timestamp,
            'x-duitku-merchantcode' => $this->setting->merchantCode(),
        ])->post($this->baseUrl().'/api/merchant/createinvoice', $payload);

        $data = $response->json() ?? [];

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

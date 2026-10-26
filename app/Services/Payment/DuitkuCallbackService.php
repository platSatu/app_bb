<?php

namespace App\Services\Payment;

use App\Models\PaymentCallback;
use App\Services\Catalog\CatalogOrderService;
use App\Services\Deposit\DepositService;
use App\Services\Invoice\InvoiceCheckoutService;
use App\Services\Invoice\InvoicePaymentService;
use App\Services\Ticket\TicketOrderService;
use Illuminate\Support\Str;
use Throwable;

/**
 * Satu pintu callback Duitku: dicatat, tanda tangan dicek, lalu diteruskan
 * ke pemilik nomor transaksinya berdasarkan awalan (TIX = tiket, ORD = katalog, INV = invoice, TGH = bayar beberapa tagihan sekaligus, selain itu top up).
 * Setiap callback (termasuk yang dikirim ulang) selalu punya baris riwayat dengan hasilnya; callback ganda aman karena
 * tiap pemilik mengunci barisnya & hanya memproses yang masih terbuka, dan kredit saldo memakai idempotency key.
 */
class DuitkuCallbackService
{
    public function __construct(
        private readonly DepositService $deposits,
        private readonly TicketOrderService $tickets,
        private readonly InvoicePaymentService $invoices,
        private readonly InvoiceCheckoutService $checkouts,
        private readonly CatalogOrderService $catalog,
    ) {
    }

    /** @return array{0: int, 1: string} [http status, body] */
    public function handle(array $payload, ?string $ip): array
    {
        $log = PaymentCallback::create([
            'provider' => 'duitku',
            'merchant_order_id' => $payload['merchantOrderId'] ?? null,
            'payload' => collect($payload)->except('signature')->all(),
            'ip_address' => $ip,
        ]);

        try {
            $client = DuitkuClient::make(forCallback: true);

            if (! $client->verifySignature($payload)) {
                $log->update(['result' => 'invalid_signature']);

                return [400, 'Invalid signature'];
            }

            $log->update(['signature_valid' => true]);

            $orderId = (string) $payload['merchantOrderId'];

            return match (true) {
                str_starts_with($orderId, TicketOrderService::PREFIX) => $this->tickets->handleCallback($payload, $log, $client),
                str_starts_with($orderId, CatalogOrderService::PREFIX) => $this->catalog->handleCallback($payload, $log, $client),
                str_starts_with($orderId, InvoicePaymentService::PREFIX) => $this->invoices->handleCallback($payload, $log, $client),
                str_starts_with($orderId, InvoiceCheckoutService::PREFIX) => $this->checkouts->handleCallback($payload, $log, $client),
                default => $this->deposits->handleCallback($payload, $log, $client),
            };
        } catch (Throwable $e) {
            // Tetap tercatat di riwayat; 500 membuat Duitku mengirim ulang, dan pengiriman ulang aman (idempoten).
            report($e);
            $log->update(['result' => 'error', 'message' => Str::limit($e->getMessage(), 250, '')]);

            return [500, 'Error'];
        }
    }
}

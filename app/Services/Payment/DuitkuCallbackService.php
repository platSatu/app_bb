<?php

namespace App\Services\Payment;

use App\Models\PaymentCallback;
use App\Services\Deposit\DepositService;
use App\Services\Invoice\InvoiceCheckoutService;
use App\Services\Invoice\InvoicePaymentService;
use App\Services\Ticket\TicketOrderService;

/**
 * Satu pintu callback Duitku: dicatat, tanda tangan dicek, lalu diteruskan
 * ke pemilik nomor transaksinya berdasarkan awalan (TIX = tiket, INV = invoice, TGH = bayar beberapa tagihan sekaligus, selain itu top up).
 */
class DuitkuCallbackService
{
    public function __construct(
        private readonly DepositService $deposits,
        private readonly TicketOrderService $tickets,
        private readonly InvoicePaymentService $invoices,
        private readonly InvoiceCheckoutService $checkouts,
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

        $client = DuitkuClient::make(forCallback: true);

        if (! $client->verifySignature($payload)) {
            $log->update(['result' => 'invalid_signature']);

            return [400, 'Invalid signature'];
        }

        $log->update(['signature_valid' => true]);

        $orderId = (string) $payload['merchantOrderId'];

        return match (true) {
            str_starts_with($orderId, TicketOrderService::PREFIX) => $this->tickets->handleCallback($payload, $log, $client),
            str_starts_with($orderId, InvoicePaymentService::PREFIX) => $this->invoices->handleCallback($payload, $log, $client),
            str_starts_with($orderId, InvoiceCheckoutService::PREFIX) => $this->checkouts->handleCallback($payload, $log, $client),
            default => $this->deposits->handleCallback($payload, $log, $client),
        };
    }
}

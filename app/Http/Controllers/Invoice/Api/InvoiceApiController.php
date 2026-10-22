<?php

namespace App\Http\Controllers\Invoice\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceApiClient;
use App\Models\InvoiceSetting;
use App\Services\Invoice\InvoicePaymentService;
use App\Services\Invoice\InvoiceService;
use App\Services\Wallet\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** REST API Invoice untuk website user. Semua data dibatasi ke branch milik API key. */
class InvoiceApiController extends Controller
{
    // Website pemanggil biasanya menunggu 20-30 detik; bizbos harus sudah membalas jauh sebelum itu.
    private const DUITKU_TIMEOUT = 8;

    public function __construct(private readonly InvoiceService $invoices, private readonly InvoicePaymentService $payments)
    {
    }

    public function store(Request $request): JsonResponse
    {
        $client = $this->client($request);
        $setting = InvoiceSetting::current();

        $data = $request->validate([
            'external_id' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9._:\-]+$/'],
            'amount' => ['required', 'integer', "min:{$setting->min_amount}", "max:{$setting->max_amount}"],
            'description' => ['required', 'string', 'max:255'],
            'customer' => ['required', 'array'],
            'customer.name' => ['required', 'string', 'max:150'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'customer.phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{8,20}$/'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            'expiry_minutes' => ['nullable', 'integer', 'min:5', 'max:43200'],
            'return_url' => ['nullable', 'url:https,http', 'max:500'],
            'send_email' => ['nullable', 'boolean'],
        ]);

        [$invoice, $created] = $this->invoices->createFromApi($client, $data, $setting->api_expiry_minutes);

        if ($created && $request->boolean('send_email')) {
            $this->invoices->email($invoice, 'issued');
        }

        // Order Duitku langsung dibuat (atau dipakai ulang kalau masih berlaku) supaya payment_url siap.
        // Gagal ke Duitku tidak menggagalkan API: dicoba lagi saat customer menekan "Bayar".
        if ($invoice->isUnpaid()) {
            try {
                $this->payments->checkout($invoice, route('invoice.pay.return', $invoice->token), self::DUITKU_TIMEOUT);
            } catch (RuntimeException) {
                // Sudah dicatat di log oleh InvoicePaymentService; payment_url = null, checkout_url tetap bisa dipakai.
            }
        }

        return response()->json(['success' => true, 'created' => $created, 'data' => $invoice->refresh()->toApi()], $created ? 201 : 200);
    }

    public function show(Request $request, string $invoice): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->find($request, $invoice)->toApi()]);
    }

    public function cancel(Request $request, string $invoice): JsonResponse
    {
        $model = $this->find($request, $invoice);

        if ($model->status === Invoice::STATUS_CANCELLED) {
            return response()->json(['success' => true, 'data' => $model->toApi()]);
        }

        try {
            $this->invoices->cancel($model, (string) $request->input('reason', 'Dibatalkan lewat API'));
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 409);
        }

        return response()->json(['success' => true, 'data' => $model->refresh()->toApi()]);
    }

    public function balance(Request $request, WalletService $wallets): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['balance' => $wallets->balanceOf($this->client($request)->branch->user), 'currency' => 'IDR']]);
    }

    /** {invoice} boleh id bizbos atau external_id milik Anda. */
    private function find(Request $request, string $id): Invoice
    {
        return Invoice::where('api_client_id', $this->client($request)->getKey())
            ->where(fn ($q) => $q->where('external_id', $id)->when(preg_match('/^[0-9a-f-]{36}$/i', $id), fn ($w) => $w->orWhere('id', $id)))
            ->firstOr(fn () => abort(response()->json(['success' => false, 'message' => 'Invoice tidak ditemukan.'], 404)));
    }

    private function client(Request $request): InvoiceApiClient
    {
        return $request->attributes->get('invoiceClient');
    }
}

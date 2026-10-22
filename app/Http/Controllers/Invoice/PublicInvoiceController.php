<?php

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Invoice\InvoicePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Halaman bayar publik /pay/{token}. Token 48 karakter acak = satu-satunya kunci, tidak bisa ditebak.
 * Bisa dibuka biasa atau sebagai popup dari website user (bizbos-pay.js); kabar ke website
 * hanya dikirim ke origin yang didaftarkan di API & Integrasi.
 */
class PublicInvoiceController extends Controller
{
    public function __construct(private readonly InvoicePaymentService $payments)
    {
    }

    public function show(Request $request, string $token): Response
    {
        $invoice = $this->find($token);

        if ($request->boolean('returned')) {
            $this->refresh($invoice);
        }

        $invoice->refresh()->load(['company', 'category', 'branch']);

        return response()->view('invoice.public.pay', [
            'invoice' => $invoice,
            'origin' => $this->origin($request, $invoice),
            // Langsung ke Duitku (tanpa klik "Bayar"), kecuali baru kembali dari Duitku atau ada error.
            'autoPay' => $invoice->isUnpaid() && ! $request->boolean('returned') && ! session()->has('error'),
            'autoPayUrl' => $invoice->openPaymentUrl(),
        ])->withHeaders($this->headers());
    }

    public function checkout(Request $request, string $token): RedirectResponse
    {
        $invoice = $this->find($token);
        $returnUrl = route('invoice.pay.return', array_filter(['token' => $token, 'origin' => $this->origin($request, $invoice)]));

        try {
            $payment = $this->payments->checkout($invoice, $returnUrl);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->away($payment->payment_url);
    }

    /** returnUrl dari Duitku: langsung cek status ke Duitku, lalu tampilkan hasilnya. */
    public function returned(Request $request, string $token): RedirectResponse
    {
        $invoice = $this->find($token);
        $this->refresh($invoice);

        $origin = $this->origin($request, $invoice);

        return redirect()->route('invoice.pay', array_filter(['token' => $token, 'returned' => 1, 'popup' => $origin ? 1 : null, 'origin' => $origin]));
    }

    public function status(string $token): JsonResponse
    {
        $invoice = $this->find($token);
        $this->refresh($invoice);

        return response()->json(['status' => $invoice->refresh()->status])->withHeaders($this->headers());
    }

    private function find(string $token): Invoice
    {
        abort_unless(strlen($token) === 48, 404);

        return Invoice::with(['apiClient', 'category', 'company', 'branch'])->where('token', $token)->firstOrFail();
    }

    /** Cek ke Duitku paling sering 1x per 15 detik per invoice. */
    private function refresh(Invoice $invoice): void
    {
        if (Cache::add('invoice-refresh:'.$invoice->getKey(), 1, 15)) {
            $this->payments->refresh($invoice);
        }
    }

    private function origin(Request $request, Invoice $invoice): ?string
    {
        $origin = (string) $request->query('origin');

        return $invoice->apiClient?->allowsOrigin($origin) ? rtrim($origin, '/') : null;
    }

    private function headers(): array
    {
        return [
            'X-Frame-Options' => 'DENY',
            'Content-Security-Policy' => "frame-ancestors 'none'",
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex, nofollow',
        ];
    }
}

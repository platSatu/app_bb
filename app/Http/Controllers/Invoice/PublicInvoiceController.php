<?php

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Services\Invoice\InvoiceFees;
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
    public function __construct(private readonly InvoicePaymentService $payments, private readonly InvoiceFees $fees)
    {
    }

    public function show(Request $request, string $token): Response
    {
        $invoice = $this->find($token);
        $returned = $request->boolean('returned');

        if ($returned) {
            $this->refresh($invoice);
        }

        // Lewat jatuh tempo tanpa denda: tutup sekarang juga, tidak menunggu scheduler 5 menitan.
        $this->payments->closeIfPastDue($invoice);

        // 5 Nov 2026: relasi sudah dimuat find(); cukup baca ulang kolom invoice (status bisa berubah di atas).
        $invoice->setRawAttributes(Invoice::whereKey($invoice->getKey())->firstOrFail()->getAttributes(), true);

        // Satu query untuk percobaan bayar yang masih terbuka (tombol "Lanjutkan pembayaran", timer, auto-redirect).
        $openPayment = $invoice->isUnpaid()
            ? $invoice->payments()->where('status', InvoicePayment::STATUS_PENDING)->where('expires_at', '>', now())
                ->whereNotNull('payment_url')->latest()->first()
            : null;

        return response()->view('invoice.public.pay', [
            'invoice' => $invoice,
            'origin' => $this->origin($request, $invoice),
            'returned' => $returned,
            'openPayment' => $openPayment,
            // Biaya "ditanggung pembayar" (aturan per customer): order yang sedang terbuka memakai snapshot-nya.
            'payerFee' => $invoice->isUnpaid() ? ($openPayment?->service_fee ?? $this->fees->payerFee($invoice->branch_id, $invoice->totalDue())) : null,
            // Timer di halaman: batas order Duitku yang sedang terbuka, kalau tidak ada: batas invoice (invoice API).
            // Hanya kalau masih di masa depan (yang sudah lewat tidak ditampilkan, supaya tidak memuat ulang terus).
            'deadline' => $invoice->isUnpaid() ? collect([$openPayment?->expires_at ?? $invoice->expires_at])->filter(fn ($d) => $d?->isFuture())->first() : null,
            // Batas invoice sudah lewat, tidak ada order yang masih berjalan: tidak bisa membuat pembayaran baru,
            // tapi status bisa saja masih berubah jadi lunas (bank telat mengirim konfirmasi).
            'timeUp' => $invoice->isUnpaid() && ! $openPayment && $invoice->expires_at?->isPast(),
            // Langsung ke Duitku (tanpa klik "Bayar"), kecuali baru kembali dari Duitku atau ada error.
            'autoPay' => $invoice->isUnpaid() && ! $returned && ! session()->has('error') && ! ($invoice->expires_at?->isPast() && ! $openPayment),
            'autoPayUrl' => $openPayment?->payment_url,
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
            // 5 Nov 2026: CSP lengkap. Halaman bayar hanya memuat skrip & gaya miliknya sendiri (inline) + font Google.
            // form-action tidak dibatasi: tombol Bayar diarahkan server ke domain Duitku.
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
                ."font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self'; base-uri 'none'; object-src 'none'; frame-ancestors 'none'",
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex, nofollow',
        ];
    }
}

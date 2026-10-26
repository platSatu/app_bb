<?php

use App\Models\InvoicePayment;
use App\Models\PaymentGatewaySetting;
use App\Services\Invoice\InvoicePaymentService;
use App\Services\Invoice\InvoiceService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    PaymentGatewaySetting::duitku()->forceFill(['sandbox_merchant_code' => 'DS123', 'sandbox_api_key' => 'secret-key', 'is_active' => true])->save();
    Http::fake(['*/createinvoice' => Http::response(['paymentUrl' => 'https://pay.test/x', 'reference' => 'REF1', 'statusMessage' => 'SUCCESS'])]);
});

it('checkout invoice antre per invoice: yang datang bersamaan menunggu, lalu memakai order Duitku yang sama', function () {
    $branch = appBranch(['invoices']);
    $invoice = DB::transaction(fn () => app(InvoiceService::class)->createForForm($branch, 'Pendaftaran', 'Budi', '6281234567890', 50000));
    $service = app(InvoicePaymentService::class);

    // Permintaan lain sedang memproses invoice ini: yang baru menunggu, lalu menyerah dengan pesan ramah (tidak membuat order baru).
    $lock = Cache::lock('invoice-checkout:'.$invoice->id, 30);
    $lock->get();
    expect(fn () => $service->checkout($invoice, 'https://app.test/return', timeout: -4))->toThrow(RuntimeException::class, 'sedang disiapkan');
    $lock->release();

    $first = $service->checkout($invoice, 'https://app.test/return');
    $second = $service->checkout($invoice, 'https://app.test/return');

    expect($second->id)->toBe($first->id)
        ->and(InvoicePayment::where('invoice_id', $invoice->id)->count())->toBe(1);
    Http::assertSentCount(1);
});

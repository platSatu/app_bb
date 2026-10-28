<?php

use App\Models\Invoice;
use App\Models\InvoiceApiClient;
use App\Models\InvoicePayment;
use App\Models\InvoiceWebhookLog;
use App\Models\PaymentCallback;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserCompany;
use Illuminate\Support\Str;

function paidInvoiceWithCallback(?string $apiClientId = null): Invoice
{
    $owner = User::factory()->create();
    $company = tap((new UserCompany())->forceFill(['user_id' => $owner->id, 'name' => 'Yayasan Uji', 'slug' => 'uji-'.Str::lower(Str::random(4))]))->save();
    $branch = tap((new UserBranch())->forceFill(['user_id' => $owner->id, 'company_id' => $company->id, 'name' => 'Cabang Uji', 'status' => 'active']))->save();

    $invoice = tap((new Invoice())->forceFill([
        'branch_id' => $branch->id, 'company_id' => $company->id, 'api_client_id' => $apiClientId, 'source' => $apiClientId ? 'api' : 'dashboard',
        'number' => 'INV-'.Str::upper(Str::random(8)), 'token' => Str::random(48), 'description' => 'SPP Januari',
        'customer_name' => 'Budi', 'amount' => 100000, 'status' => 'paid',
        'paid_amount' => 100000, 'fee' => 2000, 'net_amount' => 98000, 'paid_at' => now(),
    ]))->save();

    tap((new InvoicePayment())->forceFill([
        'invoice_id' => $invoice->id, 'merchant_order_id' => 'INV261028ABCDEFGHIJ', 'amount' => 100000,
        'status' => 'paid', 'expires_at' => now()->addHour(), 'paid_at' => now(),
    ]))->save();

    PaymentCallback::create([
        'provider' => 'duitku', 'merchant_order_id' => 'INV261028ABCDEFGHIJ', 'signature_valid' => true, 'result' => 'paid',
        'payload' => ['merchantOrderId' => 'INV261028ABCDEFGHIJ', 'amount' => '95000', 'resultCode' => '00', 'reference' => 'DREF123', 'paymentCode' => 'BC'],
    ]);

    return $invoice;
}

beforeEach(function () {
    $this->superadmin = User::factory()->create(['user_type' => User::TYPE_SUPERADMIN]);
});

it('detail invoice menampilkan callback Duitku dan menandai nominal yang tidak cocok', function () {
    $invoice = paidInvoiceWithCallback();

    $this->actingAs($this->superadmin)->get(route('superadmin.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Callback dari Duitku')
        ->assertSeeInOrder(['INV261028ABCDEFGHIJ', 'Rp 100.000', 'Rp 95.000', 'Sukses (00)', 'DREF123'])
        ->assertSee('text-danger fw-semibold')
        ->assertSee('Invoice ini dibuat dari dashboard, jadi tidak ada callback ke website customer.');
});

it('detail invoice API menampilkan isi callback yang dikirim ke website customer', function () {
    $owner = User::factory()->create();
    $company = tap((new UserCompany())->forceFill(['user_id' => $owner->id, 'name' => 'Toko API', 'slug' => 'api-'.Str::lower(Str::random(4))]))->save();
    $branch = tap((new UserBranch())->forceFill(['user_id' => $owner->id, 'company_id' => $company->id, 'name' => 'Toko', 'status' => 'active']))->save();
    $client = tap((new InvoiceApiClient())->forceFill([
        'branch_id' => $branch->id, 'api_key' => Str::random(40), 'secret' => 'rahasia', 'webhook_url' => 'https://toko.test/hook', 'webhook_secret' => 'rahasia',
    ]))->save();
    $invoice = paidInvoiceWithCallback($client->id);

    tap((new InvoiceWebhookLog())->forceFill([
        'api_client_id' => $client->id, 'invoice_id' => $invoice->id, 'event' => 'invoice.paid', 'attempt' => 1, 'http_status' => 200,
        'payload' => json_encode(['event' => 'invoice.paid', 'data' => ['status' => 'paid', 'total' => 100000, 'fee' => 2000, 'net_amount' => 98000]]),
        'response_body' => '{"ok":true}',
    ]))->save();

    $this->actingAs($this->superadmin)->get(route('superadmin.invoices.show', $invoice))
        ->assertOk()
        ->assertSee('Callback ke Website Customer')
        ->assertSeeInOrder(['invoice.paid', 'HTTP 200', 'paid', 'Rp 100.000', 'Rp 2.000', 'Rp 98.000'])
        ->assertSee('{&quot;ok&quot;:true}', false);
});

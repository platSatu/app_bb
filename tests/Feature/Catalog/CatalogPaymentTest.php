<?php

use App\Models\BranchSubscription;
use App\Models\CatalogOrder;
use App\Models\CatalogProduct;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\PaymentCallback;
use App\Models\PaymentGatewaySetting;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserCompany;
use App\Models\WalletTransaction;
use App\Services\Catalog\CatalogOrderService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function catalogShop(array $payment): array
{
    $owner = User::factory()->create();
    $company = (new UserCompany())->forceFill(['user_id' => $owner->id, 'name' => 'Toko Maju', 'slug' => 'toko-'.Str::lower(Str::random(6))]);
    $company->save();
    $branch = (new UserBranch())->forceFill(['user_id' => $owner->id, 'company_id' => $company->id, 'name' => 'Pusat', 'status' => 'active']);
    $branch->save();
    (new BranchSubscription())->forceFill(['branch_id' => $branch->id, 'app_key' => 'pages', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()])->save();
    $type = PageType::create(['name' => 'Katalog '.Str::random(4), 'template' => PageType::TEMPLATE_CATALOG, 'status' => 'active']);
    $page = (new PageCategory())->forceFill([
        'branch_id' => $branch->id, 'page_type_id' => $type->id, 'name' => 'Toko Kue', 'slug' => 'toko-'.Str::lower(Str::random(6)),
        'status' => 'active', 'start_date' => today(), 'settings' => ['catalog' => ['payment' => $payment]],
    ]);
    $page->save();
    $product = new CatalogProduct(['name' => 'Brownies', 'price' => 50000, 'stock' => 5, 'status' => 'active']);
    $product->forceFill(['page_id' => $page->id, 'slug' => 'brownies'])->save();

    $gateway = PaymentGatewaySetting::duitku();
    $gateway->forceFill(['sandbox_merchant_code' => 'DS123', 'sandbox_api_key' => 'secret-key', 'is_active' => true])->save();

    return [$owner, $page->fresh(['type', 'branch']), $product];
}

function catalogCallback(CatalogOrder $order, string $resultCode = '00')
{
    $amount = (string) $order->total;

    return test()->post(route('payment.duitku.callback'), [
        'merchantCode' => 'DS123', 'amount' => $amount, 'merchantOrderId' => $order->reference, 'paymentCode' => 'SP',
        'resultCode' => $resultCode, 'reference' => 'DUITKU-'.$order->reference,
        'signature' => hash_hmac('sha256', 'DS123'.$amount.$order->reference, 'secret-key'),
    ]);
}

beforeEach(fn () => Http::fake([
    '*/createinvoice' => Http::response(['paymentUrl' => 'https://pay.test/x', 'reference' => 'REF1', 'statusMessage' => 'SUCCESS']),
    '*/transactionStatus' => fn ($request) => Http::response(['statusCode' => '00', 'amount' => '100000']),
]));

it('transfer bank: pembeli diarahkan ke halaman pesanan berisi rekening tujuan', function () {
    [, $page, $product] = catalogShop(['transfer' => true, 'online' => false, 'banks' => [['bank' => 'BCA', 'number' => '1234567890', 'holder' => 'Toko Maju']]]);

    expect(CatalogOrderService::paymentMethods($page))->toBe([CatalogOrder::PAYMENT_TRANSFER]);

    $response = $this->post(route('page.checkout.store', $page->slug), [
        'items' => [$product->id => 2], 'name' => 'Budi', 'phone' => '081234567890', 'address' => 'Jl. Mawar 1', 'payment' => 'transfer',
    ]);

    $order = CatalogOrder::sole();
    $response->assertRedirect(route('page.order', [$page->slug, $order->access_token]));
    expect($order->payment_type)->toBe('transfer')->and($order->total)->toBe(100000)->and($product->fresh()->stock)->toBe(3);
    $this->get(route('page.order', [$page->slug, $order->access_token]))->assertOk()->assertSee('1234567890')->assertSee($order->reference);

    // Metode yang tidak diaktifkan owner ditolak.
    $this->post(route('page.checkout.store', $page->slug), [
        'items' => [$product->id => 1], 'name' => 'Ani', 'phone' => '081234567891', 'address' => 'Jl. Melati', 'payment' => 'online',
    ])->assertSessionHasErrors('payment');
});

it('bayar online: callback lunas mengkredit saldo owner sekali saja walau dikirim berulang, dan semua tercatat', function () {
    [$owner, $page, $product] = catalogShop(['transfer' => false, 'online' => true, 'banks' => []]);

    $this->post(route('page.checkout.store', $page->slug), [
        'items' => [$product->id => 2], 'name' => 'Budi', 'phone' => '081234567890', 'email' => 'budi@mail.test', 'address' => 'Jl. Mawar 1', 'payment' => 'online',
    ])->assertRedirect('https://pay.test/x');

    $order = CatalogOrder::sole();
    expect($order->expires_at)->not->toBeNull()->and($order->paid_at)->toBeNull();

    catalogCallback($order)->assertOk();
    catalogCallback($order)->assertOk();

    $order->refresh();
    expect($order->status)->toBe(CatalogOrder::STATUS_PAID)->and($order->paid_at)->not->toBeNull()
        ->and(WalletTransaction::where('type', WalletTransaction::TYPE_CATALOG_SALE)->count())->toBe(1)
        ->and((int) $owner->fresh()->wallet->balance)->toBe(100000)
        ->and(PaymentCallback::where('merchant_order_id', $order->reference)->pluck('result')->sort()->values()->all())->toBe(['already_processed', 'paid']);
});

it('bayar online: gagal mengembalikan stok, callback palsu ditolak dan tetap tercatat', function () {
    [, $page, $product] = catalogShop(['transfer' => false, 'online' => true, 'banks' => []]);
    $order = app(CatalogOrderService::class)->place($page, [$product->id => 2], ['name' => 'Budi', 'phone' => '081234567890', 'email' => 'budi@mail.test', 'address' => 'Jl', 'note' => null], 'online', null, null);
    expect($product->fresh()->stock)->toBe(3);

    $this->post(route('payment.duitku.callback'), [
        'merchantCode' => 'DS123', 'amount' => '100000', 'merchantOrderId' => $order->reference, 'resultCode' => '00', 'signature' => 'palsu',
    ])->assertStatus(400);

    catalogCallback($order, '01')->assertOk();

    expect($order->fresh()->status)->toBe(CatalogOrder::STATUS_CANCELLED)->and($product->fresh()->stock)->toBe(5)
        ->and(PaymentCallback::where('merchant_order_id', $order->reference)->pluck('result')->sort()->values()->all())->toBe(['failed', 'invalid_signature'])
        ->and(WalletTransaction::count())->toBe(0);
});

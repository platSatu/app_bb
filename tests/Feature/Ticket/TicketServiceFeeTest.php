<?php

use App\Models\BranchSubscription;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\PaymentGatewaySetting;
use App\Models\TicketCategory;
use App\Models\TicketOrder;
use App\Models\TicketSetting;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserCompany;
use App\Models\WalletTransaction;
use App\Services\Page\PageCategoryService;
use App\Services\Ticket\TicketOrderService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Mail::fake();
    Http::fake([
        '*/createinvoice' => Http::response(['paymentUrl' => 'https://pay.test/x', 'reference' => 'REF1', 'statusMessage' => 'SUCCESS']),
        // Duitku mengonfirmasi jumlah persis sebesar total pesanan.
        '*/transactionStatus' => fn (Request $r) => Http::response(['statusCode' => '00', 'amount' => (string) TicketOrder::where('reference', $r['merchantOrderId'])->value('total')]),
    ]);
    PaymentGatewaySetting::duitku()->forceFill(['sandbox_merchant_code' => 'DS123', 'sandbox_api_key' => 'secret-key', 'is_active' => true])->save();
    TicketSetting::current()->update(['fee_percent' => 1]);
});

/** Event tiket berbayar Rp100.000, penanggung biaya sesuai $payer. */
function paidEvent(string $payer, int $price = 100000): array
{
    $owner = User::factory()->create();
    $company = (new UserCompany())->forceFill(['user_id' => $owner->id, 'name' => 'EO Maju', 'slug' => 'eo-'.Str::lower(Str::random(6))]);
    $company->save();
    $branch = (new UserBranch())->forceFill(['user_id' => $owner->id, 'company_id' => $company->id, 'name' => 'Pusat', 'status' => 'active']);
    $branch->save();
    (new BranchSubscription())->forceFill(['branch_id' => $branch->id, 'app_key' => 'pages', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()])->save();
    $type = PageType::create(['name' => 'Tiket '.Str::random(4), 'template' => PageType::TEMPLATE_TICKET, 'status' => 'active']);
    $page = (new PageCategory())->forceFill([
        'branch_id' => $branch->id, 'page_type_id' => $type->id, 'name' => 'Konser', 'slug' => 'konser-'.Str::lower(Str::random(6)),
        'status' => 'active', 'start_date' => today(), 'settings' => ['ticket' => ['fee_payer' => $payer]],
    ]);
    $page->save();
    $category = new TicketCategory(['name' => 'Reguler', 'price' => $price, 'quota' => 50, 'max_per_order' => 5, 'status' => 'active']);
    $category->page_id = $page->id;
    $category->save();

    return [$owner, $page->fresh(['type', 'fields', 'branch']), $category];
}

function buyTickets(PageCategory $page, TicketCategory $category, int $qty): TicketOrder
{
    return app(TicketOrderService::class)->create($page, [$category->id => $qty], ['name' => 'Budi', 'email' => 'budi'.Str::random(4).'@mail.test', 'phone' => '0812'.random_int(10000000, 99999999)], [], '127.0.0.1', 'test');
}

function payTicket(TicketOrder $order)
{
    $amount = (string) $order->total;

    return test()->post(route('payment.duitku.callback'), [
        'merchantCode' => 'DS123', 'amount' => $amount, 'merchantOrderId' => $order->reference, 'paymentCode' => 'BC',
        'resultCode' => '00', 'reference' => 'DUITKU-'.$order->reference,
        'signature' => hash_hmac('sha256', 'DS123'.$amount.$order->reference, 'secret-key'),
    ]);
}

function ticketCredits(User $owner): int
{
    return (int) WalletTransaction::where('user_id', $owner->id)->where('type', WalletTransaction::TYPE_TICKET_SALE)->sum('amount');
}

it('rumus biaya: bilangan bulat, dibulatkan ke atas, tanpa selisih koma', function () {
    expect(TicketSetting::feeFor(100000, 1))->toBe(1000)
        ->and(TicketSetting::feeFor(99999, 1))->toBe(1000)
        ->and(TicketSetting::feeFor(110000, '1.10'))->toBe(1210)   // float biasa menghasilkan 1210,0000001 -> 1211
        ->and(TicketSetting::feeFor(1, '0.50'))->toBe(1)
        ->and(TicketSetting::feeFor(0, 1))->toBe(0)
        ->and(TicketSetting::feeFor(50000, 0))->toBe(0);
});

it('ditanggung penyelenggara: pembeli bayar harga tiket, saldo masuk dikurangi biaya, sekali saja', function () {
    [$owner, $page, $category] = paidEvent(TicketSetting::PAYER_ORGANIZER);
    $order = buyTickets($page, $category, 2);

    expect($order->only(['subtotal', 'fee', 'total', 'fee_payer']))->toBe(['subtotal' => 200000, 'fee' => 2000, 'total' => 200000, 'fee_payer' => 'organizer']);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'createinvoice') && $r['paymentAmount'] === 200000 && collect($r['itemDetails'])->sum('price') === 200000);

    payTicket($order)->assertOk();
    payTicket($order)->assertOk();   // callback dikirim ulang

    expect(ticketCredits($owner))->toBe(198000)->and($order->fresh()->status)->toBe(TicketOrder::STATUS_PAID);
});

it('ditanggung pembeli: biaya ditambahkan ke tagihan Duitku, penyelenggara terima harga tiket utuh', function () {
    [$owner, $page, $category] = paidEvent(TicketSetting::PAYER_BUYER);
    $order = buyTickets($page, $category, 3);

    expect($order->only(['subtotal', 'fee', 'total']))->toBe(['subtotal' => 300000, 'fee' => 3000, 'total' => 303000]);
    Http::assertSent(fn (Request $r) => str_contains($r->url(), 'createinvoice') && $r['paymentAmount'] === 303000
        && collect($r['itemDetails'])->sum('price') === 303000 && collect($r['itemDetails'])->contains('name', 'Biaya layanan'));

    payTicket($order)->assertOk();
    expect(ticketCredits($owner))->toBe(300000);
});

it('persen biaya terkunci di pesanan: perubahan tarif tidak mengubah pesanan yang sudah dibuat', function () {
    [$owner, $page, $category] = paidEvent(TicketSetting::PAYER_ORGANIZER);
    $order = buyTickets($page, $category, 1);

    TicketSetting::current()->update(['fee_percent' => 5]);
    payTicket($order)->assertOk();

    expect(ticketCredits($owner))->toBe(99000)
        ->and(buyTickets($page, $category, 1)->fee)->toBe(5000);
});

it('tiket gratis tanpa biaya dan tanpa uang masuk', function () {
    [$owner, $page, $category] = paidEvent(TicketSetting::PAYER_BUYER, 0);
    $order = buyTickets($page, $category, 1);

    expect($order->only(['subtotal', 'fee', 'total']))->toBe(['subtotal' => 0, 'fee' => 0, 'total' => 0])
        ->and($order->status)->toBe(TicketOrder::STATUS_PAID)
        ->and(ticketCredits($owner))->toBe(0);
});

it('penanggung biaya dikunci setelah ada tiket berbayar terjual', function () {
    [, $page, $category] = paidEvent(TicketSetting::PAYER_ORGANIZER);
    $save = fn (string $payer) => app(PageCategoryService::class)->save($page->fresh(), $page->branch, ['name' => $page->name, 'ticket_fee_payer' => $payer]);

    $save(TicketSetting::PAYER_BUYER);
    expect($page->fresh()->ticketFeePayer())->toBe(TicketSetting::PAYER_BUYER);

    buyTickets($page->fresh(['type', 'fields', 'branch']), $category, 1);

    expect(fn () => $save(TicketSetting::PAYER_ORGANIZER))->toThrow(ValidationException::class)
        ->and($page->fresh()->ticketFeePayer())->toBe(TicketSetting::PAYER_BUYER);
});

it('superadmin: persen dibatasi 0 sampai 20, user biasa tidak bisa mengubah', function () {
    $admin = User::factory()->create(['user_type' => User::TYPE_SUPERADMIN]);

    $this->actingAs($admin)->put(route('superadmin.ticket-fee.update'), ['fee_percent' => 25])->assertSessionHasErrors('fee_percent');
    $this->actingAs($admin)->put(route('superadmin.ticket-fee.update'), ['fee_percent' => 1.5])->assertSessionHas('success');
    expect((float) TicketSetting::current()->fee_percent)->toBe(1.5);

    $this->actingAs(User::factory()->create())->put(route('superadmin.ticket-fee.update'), ['fee_percent' => 0]);
    expect((float) TicketSetting::current()->fee_percent)->toBe(1.5);
});

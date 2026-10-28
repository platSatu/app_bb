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
    TicketSetting::current()->update(['fee_percent' => 1, 'free_allowance' => 1000]);
});

/** Event tiket berbayar Rp100.000, penanggung biaya sesuai $payer. */
function paidEvent(string $payer, int $price = 100000): array
{
    $owner = User::factory()->create();
    $company = (new UserCompany())->forceFill(['user_id' => $owner->id, 'name' => 'EO Maju', 'slug' => 'eo-'.Str::lower(Str::random(6))]);
    $company->save();
    $branch = (new UserBranch())->forceFill(['user_id' => $owner->id, 'company_id' => $company->id, 'name' => 'Pusat', 'status' => 'active']);
    $branch->save();
    (new BranchSubscription())->forceFill(['branch_id' => $branch->id, 'app_key' => 'events', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()])->save();
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

    $form = fn (array $data) => $data + ['fee_percent' => 1, 'free_allowance' => 1000];

    $this->actingAs($admin)->put(route('superadmin.ticket-fee.update'), $form(['fee_percent' => 25]))->assertSessionHasErrors('fee_percent');
    $this->actingAs($admin)->put(route('superadmin.ticket-fee.update'), $form(['free_allowance' => 0]))->assertSessionHasErrors('free_allowance');
    $this->actingAs($admin)->put(route('superadmin.ticket-fee.update'), $form(['fee_percent' => 1.5, 'free_allowance' => 500]))->assertSessionHas('success');
    expect((float) TicketSetting::current()->fee_percent)->toBe(1.5)->and(TicketSetting::current()->free_allowance)->toBe(500);

    $this->actingAs(User::factory()->create())->put(route('superadmin.ticket-fee.update'), $form(['fee_percent' => 0]));
    expect((float) TicketSetting::current()->fee_percent)->toBe(1.5);
});

/* ---------- Kategori tiket: event gratis, kunci nama & harga, riwayat ---------- */

function saldo(User $owner): int
{
    return app(\App\Services\Wallet\WalletService::class)->balanceOf($owner);
}

function rows(array $categories): array
{
    return ['categories' => array_map(fn ($c) => $c + ['max_per_order' => 5, 'status' => 'active'], $categories)];
}

it('event gratis: harga wajib Rp0 dan TOTAL kuota semua kategori tidak boleh melewati batas', function () {
    [$owner, $page, $category] = paidEvent(TicketSetting::PAYER_FREE, 0);
    $sync = fn (array $categories) => $this->actingAs($owner)->put(route('events.ticket.categories.sync', $page), rows($categories));

    $sync([['id' => $category->id, 'name' => 'Reguler', 'price' => 50000, 'quota' => 10]])->assertSessionHasErrors('categories.0.price');
    // Dipecah jadi banyak kategori kecil tetap dijumlahkan: 600 + 300 + 200 = 1.100 > 1.000.
    $sync([
        ['id' => $category->id, 'name' => 'Pagi', 'price' => 0, 'quota' => 600],
        ['name' => 'Siang', 'price' => 0, 'quota' => 300],
        ['name' => 'Malam', 'price' => 0, 'quota' => 200],
    ])->assertSessionHasErrors('categories');
    expect($page->ticketCategories()->count())->toBe(1)->and($category->fresh()->quota)->toBe(50);   // tidak ada yang tersimpan

    $sync([['id' => $category->id, 'name' => 'Pagi', 'price' => 0, 'quota' => 600], ['name' => 'Siang', 'price' => 0, 'quota' => 400]])->assertSessionHasNoErrors();
    expect((int) $page->ticketCategories()->sum('quota'))->toBe(1000)->and(saldo($owner))->toBe(0);   // tanpa biaya apa pun
});

it('event berbayar: kategori baru wajib punya harga', function () {
    [$owner, $page, $category] = paidEvent(TicketSetting::PAYER_ORGANIZER);

    $this->actingAs($owner)->put(route('events.ticket.categories.sync', $page), rows([
        ['id' => $category->id, 'name' => 'Reguler', 'price' => 100000, 'quota' => 50],
        ['name' => 'Undangan', 'price' => 0, 'quota' => 500],
    ]))->assertSessionHasErrors('categories.1.price');
    expect($page->ticketCategories()->count())->toBe(1);
});

it('sudah ada pembeli: nama & harga dikunci, kolom lain boleh diubah dan tercatat di riwayat', function () {
    [$owner, $page, $category] = paidEvent(TicketSetting::PAYER_ORGANIZER);
    $category->forceFill(['sold' => 3])->save();
    $sync = fn (array $row) => $this->actingAs($owner)->put(route('events.ticket.categories.sync', $page), rows([$row + ['id' => $category->id]]));

    $sync(['name' => 'Reguler', 'price' => 1000, 'quota' => 50])->assertSessionHasErrors('categories.0.price');
    $sync(['name' => 'VIP Baru', 'price' => 100000, 'quota' => 50])->assertSessionHasErrors('categories.0.price');
    expect($category->fresh()->only(['name', 'price']))->toBe(['name' => 'Reguler', 'price' => 100000]);

    $sync(['name' => 'Reguler', 'price' => 100000, 'quota' => 80, 'description' => 'Termasuk snack'])->assertSessionHasNoErrors();
    $change = \App\Models\TicketCategoryChange::sole();
    expect($category->fresh()->quota)->toBe(80)
        ->and($change->actor_id)->toBe($owner->id)
        ->and($change->changes)->toBe(['quota' => ['50', '80'], 'description' => ['-', 'Termasuk snack']]);
    $this->actingAs($owner)->get(route('events.ticket.categories.index', $page))->assertOk()->assertSee('Riwayat Perubahan')->assertSee('Termasuk snack');
});

it('jenis event dikunci setelah ada pesanan', function () {
    [, $page, $category] = paidEvent(TicketSetting::PAYER_BUYER);
    buyTickets($page, $category, 1);

    expect(fn () => app(PageCategoryService::class)->save($page->fresh(), $page->branch, ['name' => $page->name, 'ticket_fee_payer' => TicketSetting::PAYER_FREE]))
        ->toThrow(ValidationException::class);
    expect($page->fresh()->ticketFeePayer())->toBe(TicketSetting::PAYER_BUYER);
});

/* ---------- Checkout & callback: manipulasi data ---------- */

it('checkout: jumlah melebihi batas per orang / sisa kuota / kategori event lain ditolak', function () {
    [, $page, $category] = paidEvent(TicketSetting::PAYER_ORGANIZER);
    [, , $other] = paidEvent(TicketSetting::PAYER_ORGANIZER);
    $category->forceFill(['quota' => 3])->save();

    expect(fn () => buyTickets($page, $category, 6))->toThrow(ValidationException::class)     // maks 5 per orang
        ->and(fn () => buyTickets($page, $category, 4))->toThrow(ValidationException::class)  // sisa 3
        ->and(fn () => app(TicketOrderService::class)->create($page, [$other->id => 1], ['name' => 'X', 'email' => 'x@mail.test', 'phone' => '081200000000'], [], null, null))
        ->toThrow(ValidationException::class);
    expect(TicketOrder::count())->toBe(0)->and($category->fresh()->reserved)->toBe(0);
});

it('event gratis tidak pernah menagih walau harga di database diubah paksa', function () {
    [, $page, $category] = paidEvent(TicketSetting::PAYER_FREE, 0);
    $category->forceFill(['price' => 100000])->save();

    expect(fn () => buyTickets($page, $category, 1))->toThrow(ValidationException::class);
    expect(TicketOrder::count())->toBe(0);
});

it('callback dengan nominal yang diubah tidak dianggap lunas dan saldo tidak bertambah', function () {
    [$owner, $page, $category] = paidEvent(TicketSetting::PAYER_BUYER);
    $order = buyTickets($page, $category, 1);
    $amount = '1000';   // seharusnya 101.000

    $this->post(route('payment.duitku.callback'), [
        'merchantCode' => 'DS123', 'amount' => $amount, 'merchantOrderId' => $order->reference, 'paymentCode' => 'BC',
        'resultCode' => '00', 'reference' => 'X', 'signature' => hash_hmac('sha256', 'DS123'.$amount.$order->reference, 'secret-key'),
    ]);

    expect($order->fresh()->status)->toBe(TicketOrder::STATUS_PENDING)->and(ticketCredits($owner))->toBe(0);
});

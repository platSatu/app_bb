<?php

use App\Mail\InvoiceMail;
use App\Models\BranchSubscription;
use App\Models\Invoice;
use App\Models\InvoiceCategory;
use App\Models\InvoiceCheckout;
use App\Models\InvoiceSetting;
use App\Models\PaymentGatewaySetting;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserCompany;
use App\Models\WalletTransaction;
use App\Services\Invoice\InvoiceCheckoutService;
use App\Services\Invoice\InvoicePaymentService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

beforeEach(function () {
    Mail::fake();
    PaymentGatewaySetting::duitku()->forceFill(['mode' => 'sandbox', 'sandbox_merchant_code' => 'DS123', 'sandbox_api_key' => 'secret-key', 'expiry_minutes' => 60, 'is_active' => true])->save();
    InvoiceSetting::current()->forceFill(['fee_percent' => 1, 'fee_flat' => 1000, 'min_amount' => 10000, 'max_amount' => 100000000])->save();
    // Duitku palsu: createinvoice selalu berhasil; transactionStatus dibaca dari $GLOBALS['duitkuPaid'].
    $GLOBALS['duitkuPaid'] = [];
    Http::fake([
        '*/api/merchant/createinvoice' => fn (HttpRequest $r) => Http::response(['paymentUrl' => 'https://duitku.test/pay/'.$r['merchantOrderId'], 'reference' => 'REF'.Str::random(6), 'statusMessage' => 'SUCCESS']),
        '*/api/merchant/transactionStatus' => fn (HttpRequest $r) => Http::response(isset($GLOBALS['duitkuPaid'][$r['merchantOrderId']])
            ? ['statusCode' => '00', 'amount' => (string) $GLOBALS['duitkuPaid'][$r['merchantOrderId']]]
            : ['statusCode' => '01', 'amount' => '0']),
    ]);
});

function billBranch(string $name = 'SD Harapan', bool $active = true): UserBranch
{
    $owner = User::factory()->create();
    $company = (new UserCompany())->forceFill(['user_id' => $owner->id, 'name' => 'Yayasan '.$name, 'slug' => Str::slug($name).'-'.Str::lower(Str::random(4))]);
    $company->save();
    $branch = (new UserBranch())->forceFill(['user_id' => $owner->id, 'company_id' => $company->id, 'name' => $name, 'status' => 'active']);
    $branch->save();

    if ($active) {
        (new BranchSubscription())->forceFill(['branch_id' => $branch->id, 'app_key' => 'invoices', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()])->save();
    }

    return $branch;
}

function sppCategory(UserBranch $branch): InvoiceCategory
{
    $category = new InvoiceCategory(['name' => 'SPP 2026', 'prefix' => 'SPP', 'late_fee_mode' => 'flat', 'late_fee_flat' => 10000, 'status' => 'active']);
    $category->branch_id = $branch->id;
    $category->save();

    return $category;
}

function bill(UserBranch $branch, ?InvoiceCategory $category, string $email, string $description, int $amount, string $due, array $extra = []): Invoice
{
    $invoice = new Invoice();
    $invoice->forceFill([
        'branch_id' => $branch->id, 'company_id' => $branch->company_id, 'category_id' => $category?->id, 'source' => 'dashboard',
        'number' => 'INV-'.Str::upper(Str::random(8)), 'token' => Str::random(48), 'description' => $description,
        'customer_name' => 'Budi', 'customer_email' => $email, 'amount' => $amount, 'apply_late_fee' => true,
        'due_date' => $due, 'status' => 'unpaid', ...$extra,
    ])->save();

    return $invoice;
}

function parent(string $email = 'ortu@mail.test', bool $verified = true): User
{
    return User::factory()->create(['email' => $email, 'email_verified_at' => $verified ? now() : null]);
}

function duitkuCallback(InvoiceCheckout $checkout): array
{
    return [
        'merchantCode' => 'DS123', 'amount' => (string) $checkout->amount, 'merchantOrderId' => $checkout->merchant_order_id,
        'resultCode' => '00', 'paymentCode' => 'BC', 'reference' => 'DREF1',
        'signature' => hash_hmac('sha256', 'DS123'.$checkout->amount.$checkout->merchant_order_id, 'secret-key'),
    ];
}

it('menampilkan tagihan milik email akun saja, dengan tab per cabang', function () {
    $sd = billBranch('SD Harapan');
    $smp = billBranch('SMP Harapan');
    $spp = sppCategory($sd);
    bill($sd, $spp, 'ortu@mail.test', 'SPP Januari', 500000, '2026-01-10');
    bill($smp, null, 'ortu@mail.test', 'Uang Kegiatan', 250000, '2026-11-10');
    bill($sd, $spp, 'orang.lain@mail.test', 'SPP Rahasia', 999000, '2026-01-10');
    bill($sd, null, 'ortu@mail.test', 'Persembahan dari website', 50000, '2026-11-10', ['source' => 'api']);
    $user = parent();

    $this->actingAs($user)->get(route('bills.index'))->assertOk()
        ->assertSee('SD Harapan')->assertSee('SMP Harapan')->assertSee('SPP Januari')
        ->assertDontSee('SPP Rahasia')->assertDontSee('Persembahan dari website');
    $this->actingAs($user)->get(route('bills.index', ['cabang' => $smp->id]))->assertSee('Uang Kegiatan')->assertDontSee('SPP Januari');
    expect($user->fresh()->portalRoles()['payer'])->toBeTrue()
        ->and(parent('tanpa.tagihan@mail.test')->portalRoles()['payer'])->toBeFalse();
});

it('meminta verifikasi email sebelum menampilkan tagihan', function () {
    $sd = billBranch();
    bill($sd, sppCategory($sd), 'ortu@mail.test', 'SPP Januari', 500000, '2026-01-10');

    // Semua halaman login sekarang dijaga RequireVerifiedEmail: diarahkan ke halaman verifikasi, tagihan tidak tampil.
    $this->actingAs(parent(verified: false))->get(route('bills.index'))->assertRedirect(route('verification.notice'));
    $this->get(route('verification.notice'))->assertOk()->assertDontSee('SPP Januari');
});

it('wajib bayar berurutan dalam satu kategori, lalu satu order Duitku untuk semua', function () {
    $this->travelTo('2026-03-15 09:00');
    $sd = billBranch();
    $spp = sppCategory($sd);
    $jan = bill($sd, $spp, 'ortu@mail.test', 'SPP Januari', 500000, '2026-01-10');
    $feb = bill($sd, $spp, 'ortu@mail.test', 'SPP Februari', 500000, '2026-02-10');
    $mar = bill($sd, $spp, 'ortu@mail.test', 'SPP Maret', 500000, '2026-04-10');
    $user = parent();

    $this->actingAs($user)->from(route('bills.index'))->post(route('bills.pay'), ['branch_id' => $sd->id, 'invoices' => [$feb->id]])
        ->assertSessionHasErrors('invoices');
    expect(InvoiceCheckout::count())->toBe(0);

    $this->actingAs($user)->post(route('bills.pay'), ['branch_id' => $sd->id, 'invoices' => [$jan->id, $feb->id]])
        ->assertRedirectContains('https://duitku.test/pay/TGH');

    $checkout = InvoiceCheckout::with('items')->sole();
    // Januari & Februari lewat jatuh tempo -> denda flat 10.000 masing-masing, dibekukan saat checkout.
    expect($checkout->amount)->toBe(1020000)->and($checkout->late_fee)->toBe(20000)->and($checkout->items)->toHaveCount(2);
    Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'createinvoice')
        && $r['paymentAmount'] === 1020000 && array_sum(array_column($r['itemDetails'], 'price')) === 1020000);

    // Klik dua kali tidak membuat order kedua; halaman menampilkan "Lanjutkan Bayar".
    $this->actingAs($user)->from(route('bills.index'))->post(route('bills.pay'), ['branch_id' => $sd->id, 'invoices' => [$feb->id, $jan->id]])->assertSessionHas('error');
    expect(InvoiceCheckout::count())->toBe(1);
    $this->actingAs($user)->get(route('bills.index'))->assertSee('Lanjutkan Bayar')->assertSee($checkout->payment_url, false);

    // Maret tidak bisa dibayar terpisah selama Jan/Feb masih diproses (ditolak sebagai urutan / sedang diproses).
    $this->actingAs($user)->from(route('bills.index'))->post(route('bills.pay'), ['branch_id' => $sd->id, 'invoices' => [$feb->id, $mar->id, $jan->id]])
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'sedang menunggu pembayaran'));
    // Link bayar biasa untuk Januari juga ditolak supaya tidak terbayar dua kali.
    expect(fn () => app(InvoicePaymentService::class)->checkout($jan, 'https://x.test'))->toThrow(RuntimeException::class, 'menu Tagihan');
    $this->travelBack();
});

it('callback lunas: semua tagihan lunas bersamaan, saldo owner masuk sekali, satu email', function () {
    $sd = billBranch();
    $spp = sppCategory($sd);
    $jan = bill($sd, $spp, 'ortu@mail.test', 'SPP Januari', 500000, now()->addDays(5)->toDateString());
    $feb = bill($sd, $spp, 'ortu@mail.test', 'SPP Februari', 500000, now()->addMonth()->toDateString());
    $user = parent();
    $this->actingAs($user)->post(route('bills.pay'), ['branch_id' => $sd->id, 'invoices' => [$jan->id, $feb->id]]);
    $checkout = InvoiceCheckout::sole();
    $GLOBALS['duitkuPaid'][$checkout->merchant_order_id] = $checkout->amount;

    $this->post(route('payment.duitku.callback'), duitkuCallback($checkout))->assertOk();
    $this->post(route('payment.duitku.callback'), duitkuCallback($checkout))->assertOk(); // callback ganda

    $checkout->refresh();
    $fee = InvoiceSetting::current()->feeFor(1000000); // 1% + 1.000 = 11.000
    expect($checkout->status)->toBe('paid')->and($checkout->fee)->toBe($fee)->and($checkout->net_amount)->toBe(1000000 - $fee)
        ->and($jan->fresh()->status)->toBe('paid')->and($feb->fresh()->status)->toBe('paid')
        ->and($jan->fresh()->fee + $feb->fresh()->fee)->toBe($fee)
        ->and($jan->fresh()->net_amount + $feb->fresh()->net_amount)->toBe(1000000 - $fee)
        ->and(WalletTransaction::where('type', 'invoice_payment')->count())->toBe(1)
        ->and((int) WalletTransaction::where('type', 'invoice_payment')->value('amount'))->toBe(1000000 - $fee)
        ->and($sd->user->wallet->fresh()->balance)->toBe(1000000 - $fee);
    // Email bukti bayar memakai email invoice yang sudah ada (satu per tagihan), sama seperti bayar biasa.
    Mail::assertQueued(InvoiceMail::class, 2);

    $this->actingAs($user)->get(route('bills.return', $checkout))->assertRedirect()->assertSessionHas('success');
    $this->actingAs($user)->get(route('bills.index', ['lihat' => 'riwayat']))->assertSee('SPP Januari')->assertSee('SPP Februari');
});

it('tagihan yang keburu lunas lewat link lain ditandai perlu refund, bukan lunas dua kali', function () {
    $sd = billBranch();
    $jan = bill($sd, sppCategory($sd), 'ortu@mail.test', 'SPP Januari', 500000, now()->addDays(5)->toDateString());
    $user = parent();
    $this->actingAs($user)->post(route('bills.pay'), ['branch_id' => $sd->id, 'invoices' => [$jan->id]]);
    $checkout = InvoiceCheckout::sole();
    $jan->forceFill(['status' => 'paid', 'paid_amount' => 500000, 'paid_at' => now()])->save();
    $GLOBALS['duitkuPaid'][$checkout->merchant_order_id] = $checkout->amount;

    $this->post(route('payment.duitku.callback'), duitkuCallback($checkout))->assertOk();

    expect($checkout->fresh()->status)->toBe('refund_needed')
        ->and($checkout->items()->value('result'))->toBe('refund_needed')
        ->and(WalletTransaction::where('type', 'invoice_payment')->value('description'))->toContain('mohon refund');
});

it('pembayaran yang tidak dibayar sampai lewat batas ditutup, tagihannya bisa dibayar lagi', function () {
    $sd = billBranch();
    $jan = bill($sd, sppCategory($sd), 'ortu@mail.test', 'SPP Januari', 500000, now()->addDays(5)->toDateString());
    $user = parent();
    $this->actingAs($user)->post(route('bills.pay'), ['branch_id' => $sd->id, 'invoices' => [$jan->id]]);

    $this->travel(2)->hours();
    expect(app(InvoiceCheckoutService::class)->expireStale())->toBe(['paid' => 0, 'expired' => 1])
        ->and(InvoiceCheckout::sole()->status)->toBe('expired')->and($jan->fresh()->status)->toBe('unpaid');

    $this->actingAs($user)->post(route('bills.pay'), ['branch_id' => $sd->id, 'invoices' => [$jan->id]])->assertRedirectContains('duitku.test');
    expect(InvoiceCheckout::count())->toBe(2);
});

it('akun lain tidak bisa membayar atau melihat pembayaran orang lain', function () {
    $sd = billBranch();
    $jan = bill($sd, sppCategory($sd), 'ortu@mail.test', 'SPP Januari', 500000, now()->addDays(5)->toDateString());
    bill($sd, null, 'tetangga@mail.test', 'Tagihan tetangga', 100000, now()->addDays(5)->toDateString());
    $owner = parent();
    $this->actingAs($owner)->post(route('bills.pay'), ['branch_id' => $sd->id, 'invoices' => [$jan->id]]);
    $checkout = InvoiceCheckout::sole();
    $stranger = parent('tetangga@mail.test');

    $this->actingAs($stranger)->get(route('bills.return', $checkout))->assertNotFound();
    $this->actingAs($stranger)->from(route('bills.index'))->post(route('bills.pay'), ['branch_id' => $sd->id, 'invoices' => [$jan->id]])
        ->assertSessionHas('error', fn ($m) => str_contains($m, 'sudah berubah'));
});

it('cabang yang layanan invoicenya habis tidak bisa dibayar online', function () {
    $sd = billBranch('SD Lama', active: false);
    $jan = bill($sd, sppCategory($sd), 'ortu@mail.test', 'SPP Januari', 500000, now()->addDays(5)->toDateString());
    $user = parent();

    $this->actingAs($user)->get(route('bills.index'))->assertSee('sedang tidak tersedia');
    $this->actingAs($user)->from(route('bills.index'))->post(route('bills.pay'), ['branch_id' => $sd->id, 'invoices' => [$jan->id]])
        ->assertSessionHas('error');
    expect(InvoiceCheckout::count())->toBe(0);
});

it('menu Tagihan Saya muncul untuk pembayar', function () {
    $sd = billBranch();
    bill($sd, sppCategory($sd), 'ortu@mail.test', 'SPP Januari', 500000, now()->subDays(3)->toDateString());

    $this->actingAs(parent())->get(route('dashboard'))->assertOk()->assertSee('Tagihan Saya')->assertSee('Mau punya usaha sendiri juga?');
});

it('menu Customers tampil per aplikasi aktif, tidak untuk pembayar saja', function () {
    $sd = billBranch();
    bill($sd, sppCategory($sd), 'ortu@mail.test', 'SPP Januari', 500000, now()->subDays(3)->toDateString());

    $this->actingAs(parent())->get(route('dashboard'))->assertOk()->assertDontSee(route('invoices.customers.index'));
    $this->get(route('invoices.customers.index'))->assertRedirect(route('profile.packages'));

    // Owner dengan paket Invoice: Customers Invoice ada, Customers Pages belum (paket Pages tidak aktif).
    $this->actingAs($sd->user)->get(route('dashboard'))->assertOk()->assertSee(route('invoices.customers.index'))->assertDontSee(route('pages.customers.index'));
    $this->get(route('invoices.customers.index'))->assertOk();
});

<?php

use App\Models\CategoryApplication;
use App\Models\Package;
use App\Models\Referral;
use App\Models\ReferralCommission;
use App\Models\ReferralSetting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Referral\ReferralService;
use App\Services\Subscription\SubscriptionService;
use App\Services\Wallet\WalletService;

beforeEach(function () {
    ReferralSetting::current()->forceFill(['is_active' => true, 'default_percent' => 20, 'hold_days' => 30, 'active_months' => 12])->save();
    $this->package = Package::create([
        'category_application_id' => CategoryApplication::create(['name' => 'Pages', 'status' => 'active'])->id,
        'name' => 'Pages - 6 Bulan', 'price' => 1000000, 'duration' => 180, 'apps' => ['pages'], 'status' => 'active',
    ]);
    $this->referrals = app(ReferralService::class);
    $this->wallets = app(WalletService::class);
});

/** A mengajak B; B punya saldo 2 juta. */
function referralPair(): array
{
    $a = User::factory()->create();
    $b = User::factory()->create();
    app(ReferralService::class)->recordSignup($b, app(ReferralService::class)->codeFor($a)->code);
    app(WalletService::class)->credit($b, 2000000, WalletTransaction::TYPE_DEPOSIT, 'tes-topup:'.$b->id);

    return [$a, $b];
}

it('komisi dari pembelian paket: dibagi ke teman sebagai potongan, ditahan 30 hari, lalu cair sekali saja', function () {
    [$a, $b] = referralPair();
    $this->referrals->setShare($a, 5);

    $voucher = app(SubscriptionService::class)->purchase($b, $this->package, null);
    $commission = ReferralCommission::sole();

    // B bayar 1.000.000 - 5% = 950.000; A dapat 20% - 5% = 15% = 150.000, belum masuk saldo.
    expect($voucher->price)->toBe(950000)
        ->and($this->wallets->balanceOf($b))->toBe(1050000)
        ->and($commission->only(['discount_amount', 'paid_amount', 'commission_percent', 'share_percent', 'amount', 'status']))
        ->toBe(['discount_amount' => 50000, 'paid_amount' => 950000, 'commission_percent' => 20, 'share_percent' => 5, 'amount' => 150000, 'status' => ReferralCommission::STATUS_HELD])
        ->and($this->wallets->balanceOf($a))->toBe(0);

    expect($this->referrals->releaseDue())->toBe(0);           // belum 30 hari
    $this->travel(31)->days();
    expect($this->referrals->releaseDue())->toBe(1)
        ->and($this->referrals->releaseDue())->toBe(0)          // dipanggil ulang: tidak cair dua kali
        ->and($this->referrals->release($commission->id))->toBeFalse()
        ->and($this->wallets->balanceOf($a))->toBe(150000)
        ->and(WalletTransaction::where('user_id', $a->id)->where('type', WalletTransaction::TYPE_REFERRAL_BONUS)->count())->toBe(1)
        ->and($commission->fresh()->status)->toBe(ReferralCommission::STATUS_RELEASED);
});

it('persen khusus per user dari superadmin; bagian untuk teman tidak boleh melebihi komisinya', function () {
    [$a, $b] = referralPair();
    $this->referrals->setRate($a, 70);
    app(SubscriptionService::class)->purchase($b, $this->package, null);

    expect(ReferralCommission::sole()->amount)->toBe(700000)
        ->and($this->wallets->balanceOf($b))->toBe(1000000);    // bagian teman 0% = bayar penuh

    expect(fn () => $this->referrals->setShare($a, 71))->toThrow(RuntimeException::class, 'antara 0% dan 70%');
    $this->referrals->setShare($a, 50);
    $this->referrals->setRate($a, 30);                          // persen diturunkan: bagian teman ikut turun
    expect($a->referralCode->fresh()->share_percent)->toBe(30);
});

it('komisi yang ditahan bisa dibatalkan superadmin dan tidak pernah cair; yang sudah cair tidak bisa dibatalkan', function () {
    [$a, $b] = referralPair();
    $admin = User::factory()->create(['user_type' => User::TYPE_SUPERADMIN]);
    $purchase = fn () => app(SubscriptionService::class)->purchase($b, $this->package, null);

    $purchase();
    $this->actingAs($admin)->post(route('superadmin.referral.cancel', ReferralCommission::sole()), ['reason' => 'Customer minta refund'])
        ->assertSessionHas('success');
    $this->travel(31)->days();
    expect($this->referrals->releaseDue())->toBe(0)->and($this->wallets->balanceOf($a))->toBe(0);

    $this->travelBack();
    $purchase();
    $second = ReferralCommission::where('status', ReferralCommission::STATUS_HELD)->sole();
    $this->travel(31)->days();
    $this->referrals->releaseDue();
    expect(fn () => $this->referrals->cancel($second->fresh(), $admin, 'terlambat'))->toThrow(RuntimeException::class, 'sudah cair')
        ->and($this->wallets->balanceOf($a))->toBe(200000);
});

it('tanpa komisi kalau program nonaktif, lewat 12 bulan sejak daftar, atau pengajak nonaktif saat cair', function () {
    [$a, $b] = referralPair();
    $service = app(SubscriptionService::class);

    ReferralSetting::current()->update(['is_active' => false]);
    $service->purchase($b, $this->package, null);
    expect(ReferralCommission::count())->toBe(0)->and($this->wallets->balanceOf($b))->toBe(1000000);

    ReferralSetting::current()->update(['is_active' => true]);
    Referral::where('referred_user_id', $b->id)->update(['created_at' => now()->subMonths(13)]);
    $this->wallets->credit($b, 1000000, WalletTransaction::TYPE_DEPOSIT, 'tes-topup-2:'.$b->id);
    $service->purchase($b, $this->package, null);
    expect(ReferralCommission::count())->toBe(0);

    Referral::where('referred_user_id', $b->id)->update(['created_at' => now()]);
    $this->wallets->credit($b, 1000000, WalletTransaction::TYPE_DEPOSIT, 'tes-topup-3:'.$b->id);
    $service->purchase($b, $this->package, null);
    $a->forceFill(['status' => 'inactive'])->save();
    $this->travel(31)->days();
    expect($this->referrals->releaseDue())->toBe(0)
        ->and(ReferralCommission::sole()->status)->toBe(ReferralCommission::STATUS_HELD);
});

it('pengajak mengatur bagian untuk teman dari halaman Referral', function () {
    [$a] = referralPair();

    $this->actingAs($a)->get(route('profile.referral'))->assertOk()->assertSee('Komisi Anda')->assertSee('20%');
    $this->put(route('profile.referral.share'), ['share_percent' => 10])->assertSessionHas('success');
    $this->put(route('profile.referral.share'), ['share_percent' => 25])->assertSessionHasErrors('share_percent');

    expect($a->referralCode->fresh()->share_percent)->toBe(10);
});

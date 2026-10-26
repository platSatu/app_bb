<?php

use App\Models\BankAccount;
use App\Models\DisbursementSetting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Models\WalletWithdrawal;
use App\Services\Wallet\WalletService;
use App\Services\Withdrawal\BankAccountService;
use App\Services\Withdrawal\WithdrawalService;
use Illuminate\Support\Facades\Http;

/** User bersaldo 1 juta (sudah settlement) dengan rekening aktif, tarik saldo aktif (biaya 5.000, minimal 50.000). */
function withdrawalUser(): array
{
    DisbursementSetting::current()->forceFill([
        'is_active' => true, 'mode' => 'sandbox', 'sandbox_user_id' => '123', 'sandbox_email' => 'dev@bizbos.test',
        'sandbox_secret_key' => 'secret', 'withdrawal_fee' => 5000, 'min_amount' => 50000,
    ])->save();

    $user = User::factory()->create();
    app(WalletService::class)->credit($user, 1000000, WalletTransaction::TYPE_DEPOSIT, 'test-topup:'.$user->id);

    $account = new BankAccount();
    $account->forceFill([
        'user_id' => $user->id, 'bank_code' => '014', 'bank_name' => 'BCA', 'account_number' => '1234567890',
        'account_hash' => BankAccount::hash('014', '1234567890'), 'account_last4' => '7890', 'account_name' => 'BUDI SANTOSO',
        'status' => BankAccount::STATUS_APPROVED,
    ])->save();

    return [$user, $account];
}

beforeEach(fn () => Http::preventStrayRequests());

it('nominal negatif ditolak validasi, bukan diubah jadi positif', function () {
    [$user] = withdrawalUser();

    $this->actingAs($user)->post(route('account.withdrawals.store'), ['amount' => '-100000'])->assertSessionHasErrors('amount');
    expect(WalletWithdrawal::count())->toBe(0);
});

it('pengajuan yang menunggu dibatasi per user, saldo tidak bisa ditahan berulang-ulang', function () {
    [$user] = withdrawalUser();
    $service = app(WithdrawalService::class);

    foreach (range(1, WithdrawalService::MAX_PENDING) as $i) {
        $service->request($user, 100000, 5000);
    }

    expect(fn () => $service->request($user, 100000, 5000))->toThrow(RuntimeException::class, 'menunggu diproses')
        ->and(WalletWithdrawal::where('user_id', $user->id)->count())->toBe(WithdrawalService::MAX_PENDING)
        ->and(app(WalletService::class)->balanceOf($user))->toBe(1000000 - 100000 * WithdrawalService::MAX_PENDING);
});

it('rekening yang sudah diganti tidak dikirimi dana: pengajuan gagal dan saldo kembali utuh', function () {
    [$user, $account] = withdrawalUser();
    $withdrawal = app(WithdrawalService::class)->request($user, 200000, 5000);
    expect(app(WalletService::class)->balanceOf($user))->toBe(800000);

    $account->forceFill(['status' => BankAccount::STATUS_REPLACED])->save();
    $admin = User::factory()->create(['user_type' => User::TYPE_SUPERADMIN]);

    $result = app(WithdrawalService::class)->approve($withdrawal, $admin);

    expect($result->status)->toBe(WalletWithdrawal::STATUS_FAILED)
        ->and($result->refunded_at)->not->toBeNull()
        ->and(app(WalletService::class)->balanceOf($user))->toBe(1000000);
    Http::assertNothingSent();
});

it('cek rekening ke bank dibatasi per hari supaya tidak dipakai mencari nama pemilik rekening orang lain', function () {
    [$user] = withdrawalUser();
    Http::fake([
        '*/listBank' => Http::response(['responseCode' => '00', 'Banks' => [['bankCode' => '014', 'bankName' => 'BCA']]]),
        '*/inquirysandbox' => Http::response(['responseCode' => '00', 'accountName' => 'ORANG LAIN', 'disburseId' => '1']),
    ]);
    $service = app(BankAccountService::class);

    foreach (range(1, BankAccountService::MAX_CHECKS_PER_DAY) as $i) {
        $service->submit($user, '014', '99999999'.$i);
    }

    expect(fn () => $service->submit($user, '014', '888888888'))->toThrow(RuntimeException::class, 'hari ini');
    // listBank (sekali, lalu dari cache) + 1 inquiry per pengecekan yang diizinkan.
    Http::assertSentCount(1 + BankAccountService::MAX_CHECKS_PER_DAY);
});

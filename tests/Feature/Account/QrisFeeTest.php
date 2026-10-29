<?php

use App\Models\Deposit;
use App\Models\PaymentCallback;
use App\Models\PaymentGatewaySetting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Potongan QRIS (29 Okt 2026): pembayaran Duitku yang dibayar QRIS (SP, NQ, SQ) dipotong
 * 0,7% saat masuk saldo; metode lain tidak dipotong. Alur & biaya lama tidak berubah.
 */
function qrisGateway(array $extra = []): PaymentGatewaySetting
{
    $gateway = PaymentGatewaySetting::duitku();
    $gateway->forceFill(['sandbox_merchant_code' => 'DS123', 'sandbox_api_key' => 'secret-key', 'is_active' => true] + $extra)->save();

    return $gateway->fresh();
}

function qrisDeposit(User $user, int $amount): Deposit
{
    $deposit = new Deposit();
    $deposit->forceFill([
        'user_id' => $user->id, 'reference_number' => 'DEP'.Str::upper(Str::random(10)), 'amount' => $amount,
        'status' => Deposit::STATUS_PENDING, 'expires_at' => now()->addHour(),
    ])->save();

    return $deposit;
}

function qrisDepositCallback(Deposit $deposit, string $paymentCode)
{
    $amount = (string) $deposit->amount;

    return test()->post(route('payment.duitku.callback'), [
        'merchantCode' => 'DS123', 'amount' => $amount, 'merchantOrderId' => $deposit->reference_number, 'paymentCode' => $paymentCode,
        'resultCode' => '00', 'reference' => 'DUITKU-'.$deposit->reference_number,
        'signature' => hash_hmac('sha256', 'DS123'.$amount.$deposit->reference_number, 'secret-key'),
    ]);
}

beforeEach(fn () => Http::fake([
    // Konfirmasi status ke Duitku: semua deposit di test ini Rp 100.000.
    '*/transactionStatus' => Http::response(['statusCode' => '00', 'amount' => '100000']),
]));

it('menghitung potongan QRIS hanya untuk kode QRIS dan dibulatkan ke atas', function () {
    $gateway = qrisGateway();

    expect($gateway->qrisMethods())->toBe(['SP', 'NQ', 'SQ'])
        ->and($gateway->qrisFeeFor('SP', 100000))->toBe(700)
        ->and($gateway->qrisFeeFor('nq', 100000))->toBe(700)
        ->and($gateway->qrisFeeFor('SQ', 15000))->toBe(105)
        ->and($gateway->qrisFeeFor('SP', 1))->toBe(1)
        ->and($gateway->qrisFeeFor('BC', 100000))->toBe(0)
        ->and($gateway->qrisFeeFor('OV', 100000))->toBe(0)
        ->and($gateway->qrisFeeFor(null, 100000))->toBe(0);

    $gateway->forceFill(['qris_fee_percent' => 0])->save();
    expect($gateway->fresh()->qrisFeeFor('SP', 100000))->toBe(0);

    $gateway->forceFill(['qris_fee_percent' => 1.5, 'qris_fee_methods' => ['SP', 'DQ']])->save();
    expect($gateway->fresh()->qrisFeeFor('DQ', 100000))->toBe(1500)
        ->and($gateway->fresh()->qrisFeeFor('NQ', 100000))->toBe(0);
});

it('top up dibayar QRIS: saldo masuk dikurangi 0,7% dan potongannya tercatat, callback ulang tidak memotong lagi', function () {
    qrisGateway();
    $user = User::factory()->create();
    $deposit = qrisDeposit($user, 100000);

    qrisDepositCallback($deposit, 'SP')->assertOk();
    qrisDepositCallback($deposit, 'SP')->assertOk();

    $trx = WalletTransaction::where('type', WalletTransaction::TYPE_DEPOSIT)->sole();
    expect($deposit->fresh()->status)->toBe(Deposit::STATUS_SUCCESS)
        ->and($trx->amount)->toBe(99300)
        ->and((int) $trx->method_fee)->toBe(700)
        ->and($trx->description)->toContain('potongan QRIS 0,7% Rp 700')
        ->and((int) $user->fresh()->wallet->balance)->toBe(99300)
        ->and(PaymentCallback::where('merchant_order_id', $deposit->reference_number)->pluck('result')->sort()->values()->all())->toBe(['already_processed', 'credited']);
});

it('top up dibayar Virtual Account: tidak ada potongan (biaya ditanggung customer)', function () {
    qrisGateway();
    $user = User::factory()->create();
    $deposit = qrisDeposit($user, 100000);

    qrisDepositCallback($deposit, 'BC')->assertOk();

    $trx = WalletTransaction::where('type', WalletTransaction::TYPE_DEPOSIT)->sole();
    expect($trx->amount)->toBe(100000)
        ->and((int) $trx->method_fee)->toBe(0)
        ->and($trx->description)->not->toContain('QRIS')
        ->and((int) $user->fresh()->wallet->balance)->toBe(100000);
});

it('potongan dihitung dari nominal yang dibayar, bukan dari yang masuk saldo (mis. setelah biaya layanan)', function () {
    qrisGateway();
    $user = User::factory()->create();

    // Dibayar Rp 100.000, biaya layanan Rp 4.000 -> biasanya masuk Rp 96.000; QRIS 0,7% x 100.000 = Rp 700.
    $trx = app(WalletService::class)->creditPayment($user, 96000, 100000, WalletTransaction::TYPE_INVOICE_PAYMENT, 'test:qris-net', null, 'Invoice X', 'NQ');

    expect($trx->amount)->toBe(95300)->and((int) $trx->method_fee)->toBe(700)
        ->and((int) $user->fresh()->wallet->balance)->toBe(95300);

    // Metode lain tetap masuk utuh.
    $trx2 = app(WalletService::class)->creditPayment($user, 96000, 100000, WalletTransaction::TYPE_INVOICE_PAYMENT, 'test:va-net', null, 'Invoice Y', 'M2');
    expect($trx2->amount)->toBe(96000)->and((int) $trx2->method_fee)->toBe(0);
});

<?php

use App\Jobs\ProcessDisbursement;
use App\Models\Disbursement;
use App\Models\DisbursementRecipient;
use App\Models\DisbursementSetting;
use App\Models\InvoiceApiClient;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use App\Services\Withdrawal\DisbursementService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;


beforeEach(function () {
    Queue::fake();
    Http::preventStrayRequests();
    DisbursementSetting::current()->forceFill([
        'is_active' => true, 'mode' => 'sandbox', 'sandbox_user_id' => '123', 'sandbox_email' => 'dev@bizbos.test',
        'sandbox_secret_key' => 'secret', 'withdrawal_fee' => 5000, 'min_amount' => 50000,
    ])->save();

    $this->branch = appBranch(['invoices']);
    $this->owner = User::find($this->branch->user_id);
    app(WalletService::class)->credit($this->owner, 1000000, WalletTransaction::TYPE_DEPOSIT, 'test-topup:'.$this->owner->id);

    $this->recipient = tap((new DisbursementRecipient())->forceFill([
        'branch_id' => $this->branch->id, 'external_id' => 'vendor-1', 'bank_code' => '014', 'bank_name' => 'BCA',
        'account_number' => '1234567890', 'account_hash' => hash('sha256', '1234567890'), 'account_last4' => '7890',
        'account_name' => 'BUDI SANTOSO', 'status' => DisbursementRecipient::STATUS_ACTIVE, 'source' => Disbursement::SOURCE_API,
    ]))->save();

    $this->client = tap((new InvoiceApiClient())->forceFill([
        'branch_id' => $this->branch->id, 'api_key' => 'pk_test_payout', 'secret' => 'sk_test_rahasia',
        'webhook_secret' => 'whsec_test', 'is_active' => true, 'payout_enabled' => true,
    ]))->save();
});

/** POST ke API dengan tanda tangan HMAC yang benar (path tanpa query string, sama seperti klien sungguhan). */
function signedPayout($test, string $uri, array $body)
{
    $json = json_encode($body);
    $ts = (string) time();
    $signature = hash_hmac('sha256', $ts."\nPOST\n".parse_url($uri, PHP_URL_PATH)."\n".$json, 'sk_test_rahasia');

    // postJson meng-encode body dengan json_encode yang sama, jadi isi yang ditandatangani persis sama dengan yang dikirim.
    return $test->withHeaders(['X-Bizbos-Key' => 'pk_test_payout', 'X-Bizbos-Timestamp' => $ts, 'X-Bizbos-Signature' => $signature])
        ->postJson($uri, $body);
}

it('API: external_id yang sama dikirim berkali-kali hanya memotong saldo sekali; isi berbeda ditolak 409', function () {
    $body = ['external_id' => 'pay-001', 'payout_account_id' => 'vendor-1', 'amount' => 100000];

    signedPayout($this, '/api/v1/payouts', $body)->assertCreated()->assertJsonPath('created', true);
    signedPayout($this, '/api/v1/payouts', $body)->assertOk()->assertJsonPath('created', false);
    signedPayout($this, '/api/v1/payouts', [...$body, 'amount' => 900000])->assertStatus(409);

    expect(Disbursement::count())->toBe(1)
        ->and(app(WalletService::class)->balanceOf($this->owner))->toBe(900000);
    Queue::assertPushed(ProcessDisbursement::class, 1);
});

it('API: kiriman beruntun tidak bisa melebihi saldo, saldo tidak pernah minus', function () {
    signedPayout($this, '/api/v1/payouts', ['external_id' => 'a', 'payout_account_id' => 'vendor-1', 'amount' => 600000])->assertCreated();
    signedPayout($this, '/api/v1/payouts', ['external_id' => 'b', 'payout_account_id' => 'vendor-1', 'amount' => 600000])->assertStatus(422);
    signedPayout($this, '/api/v1/payouts', ['external_id' => 'c', 'payout_account_id' => 'vendor-1', 'amount' => Disbursement::MAX_AMOUNT + 1])->assertStatus(422);

    expect(Disbursement::count())->toBe(1)
        ->and(app(WalletService::class)->balanceOf($this->owner))->toBe(400000);
});

it('API: data tambahan lewat query string (tidak ikut ditandatangani) ditolak', function () {
    signedPayout($this, '/api/v1/payouts?description=sisipan', ['external_id' => 'q-1', 'payout_account_id' => 'vendor-1', 'amount' => 100000])
        ->assertStatus(400);

    expect(Disbursement::count())->toBe(0);
});

it('dashboard: form yang sama dikirim dua kali (klik ganda / dua tab) hanya jadi satu kiriman', function () {
    $form = ['branch_id' => $this->branch->id, 'recipient_id' => $this->recipient->id, 'amount' => 100000, 'request_token' => (string) str()->uuid()];
    $this->actingAs($this->owner);

    $this->post(route('invoices.disbursements.send'), $form)->assertSessionHas('success');
    $this->post(route('invoices.disbursements.send'), $form)->assertSessionHas('info');
    $this->post(route('invoices.disbursements.send'), [...$form, 'request_token' => null])->assertSessionHasErrors('request_token');

    expect(Disbursement::count())->toBe(1)
        ->and(app(WalletService::class)->balanceOf($this->owner))->toBe(900000);
});

it('cek rekening ke bank dibatasi per branch per hari', function () {
    Http::fake(['*/listBank' => Http::response(['responseCode' => '00', 'Banks' => [['bankCode' => '014', 'bankName' => 'BCA']]])]);

    foreach (range(1, DisbursementService::ACCOUNT_CHECKS_PER_DAY) as $i) {
        RateLimiter::hit('disbursement-account-check:'.$this->branch->id, 86400);
    }

    expect(fn () => app(DisbursementService::class)->saveRecipient($this->branch, ['bank_code' => '014', 'account_number' => '5550001111'], Disbursement::SOURCE_API))
        ->toThrow(RuntimeException::class, 'Batas cek rekening');
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'inquiry'));
});

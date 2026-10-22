<?php

namespace App\Services\Deposit;

use App\Models\Deposit;
use App\Models\PaymentCallback;
use App\Models\PaymentGatewaySetting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Payment\DuitkuClient;
use App\Services\Referral\ReferralService;
use App\Services\Wallet\SettlementCalendar;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class DepositService
{
    // Callback yang telat beberapa menit setelah batas waktu masih ditunggu dulu.
    private const EXPIRY_GRACE_MINUTES = 10;

    public function __construct(
        private readonly WalletService $wallets,
        private readonly ReferralService $referrals,
    ) {
    }

    /** Buat deposit pending + invoice Duitku. Saldo BELUM bertambah di sini. */
    public function create(User $user, int $amount): Deposit
    {
        $client = DuitkuClient::make();
        $setting = PaymentGatewaySetting::duitku();

        $deposit = new Deposit();
        $deposit->forceFill([
            'user_id' => $user->getKey(),
            'reference_number' => $this->uniqueReference(),
            'amount' => $amount,
            'status' => Deposit::STATUS_PENDING,
            'expires_at' => now()->addMinutes($setting->expiry_minutes),
        ])->save();

        try {
            $invoice = $client->createInvoice([
                'amount' => $deposit->amount,
                'orderId' => $deposit->reference_number,
                'product' => 'Top Up Saldo',
                'userInfo' => (string) $user->getKey(),
                'customer' => ['name' => $user->name, 'email' => $user->email, 'phone' => $user->handphone],
                'items' => [['name' => 'Top Up Saldo', 'price' => $deposit->amount, 'quantity' => 1]],
                'returnUrl' => route('account.deposits.show', $deposit),
            ]);
        } catch (Throwable $e) {
            report($e);
            $invoice = ['paymentUrl' => null, 'reference' => null, 'message' => 'Duitku tidak bisa dihubungi'];
        }

        if (! $invoice['paymentUrl']) {
            $deposit->forceFill(['status' => Deposit::STATUS_FAILED, 'failure_reason' => $invoice['message']])->save();

            throw new RuntimeException('Pembayaran belum bisa dibuat. Silakan coba beberapa saat lagi.');
        }

        $deposit->forceFill(['payment_url' => $invoice['paymentUrl'], 'provider_reference' => $invoice['reference']])->save();

        return $deposit;
    }

    /**
     * Callback Duitku untuk nomor DEP... (sudah dicatat & tanda tangannya valid di DuitkuCallbackService).
     *
     * @return array{0: int, 1: string} [http status, body]
     */
    public function handleCallback(array $payload, PaymentCallback $log, DuitkuClient $client): array
    {
        $deposit = Deposit::where('reference_number', $payload['merchantOrderId'])->first();

        if (! $deposit) {
            $log->update(['result' => 'not_found']);

            return [200, 'OK'];
        }

        if ((int) round((float) $payload['amount']) !== $deposit->amount) {
            $log->update(['result' => 'amount_mismatch']);

            return [200, 'OK'];
        }

        if (($payload['resultCode'] ?? null) !== '00') {
            $log->update(['result' => $this->markFailed($deposit, 'Dibatalkan/gagal di Duitku')]);

            return [200, 'OK'];
        }

        if ($this->canBeCredited($deposit) && ($reason = $client->unpaidReason($deposit->reference_number, $deposit->amount))) {
            $log->update(['result' => 'not_confirmed', 'message' => $reason]);

            return [409, 'Payment not confirmed'];
        }

        $log->update(['result' => $this->markPaid($deposit, $payload['paymentCode'] ?? null, $payload['reference'] ?? null)]);

        return [200, 'OK'];
    }

    /**
     * Deposit pending yang lewat batas waktu: dicek dulu ke Duitku. Kalau
     * ternyata sudah dibayar, saldo tetap dikreditkan; kalau belum, kedaluwarsa.
     *
     * @return array{credited: int, expired: int, skipped: int}
     */
    public function expireStale(): array
    {
        $summary = ['credited' => 0, 'expired' => 0, 'skipped' => 0];
        $client = $this->clientOrNull();

        Deposit::query()
            ->where('status', Deposit::STATUS_PENDING)
            ->where('expires_at', '<', now()->subMinutes(self::EXPIRY_GRACE_MINUTES))
            ->orderBy('expires_at')
            ->chunkById(100, function ($deposits) use ($client, &$summary) {
                foreach ($deposits as $deposit) {
                    $paid = $client && $client->unpaidReason($deposit->reference_number, $deposit->amount) === null;

                    $result = $paid
                        ? $this->markPaid($deposit, null, null)
                        : $this->markExpired($deposit);

                    $summary[match ($result) {
                        'credited' => 'credited',
                        'expired' => 'expired',
                        default => 'skipped',
                    }]++;
                }
            });

        return $summary;
    }

    /** Satu-satunya jalur deposit -> saldo. Deposit dikunci, saldo + bonus referral sekali saja. */
    private function markPaid(Deposit $deposit, ?string $paymentCode, ?string $providerReference): string
    {
        return DB::transaction(function () use ($deposit, $paymentCode, $providerReference) {
            $locked = Deposit::with('user')->whereKey($deposit->getKey())->lockForUpdate()->first();

            if (! $this->canBeCredited($locked)) {
                return 'already_processed';
            }

            $locked->forceFill([
                'status' => Deposit::STATUS_SUCCESS,
                'paid_at' => now(),
                'failure_reason' => null,
                'payment_method' => $paymentCode ?? $locked->payment_method,
                'provider_reference' => $providerReference ?? $locked->provider_reference,
            ])->save();

            $this->wallets->credit(
                $locked->user,
                $locked->amount,
                WalletTransaction::TYPE_DEPOSIT,
                'deposit:'.$locked->getKey(),
                $locked,
                'Top up '.$locked->reference_number,
                paymentMethod: $locked->payment_method,
                availableAt: SettlementCalendar::availableAt($locked->payment_method),
            );

            $this->referrals->rewardForDeposit($locked);

            return 'credited';
        });
    }

    private function markFailed(Deposit $deposit, string $reason): string
    {
        return $this->closePending($deposit, Deposit::STATUS_FAILED, $reason) ? 'failed' : 'already_processed';
    }

    private function markExpired(Deposit $deposit): string
    {
        return $this->closePending($deposit, Deposit::STATUS_EXPIRED, 'Melewati batas waktu pembayaran') ? 'expired' : 'already_processed';
    }

    private function closePending(Deposit $deposit, string $status, string $reason): bool
    {
        return DB::transaction(function () use ($deposit, $status, $reason) {
            $locked = Deposit::whereKey($deposit->getKey())->lockForUpdate()->first();

            if (! $locked->isPending()) {
                return false;
            }

            $locked->forceFill(['status' => $status, 'failure_reason' => $reason])->save();

            return true;
        });
    }

    /** Pending, atau sudah kedaluwarsa tapi ternyata dibayar (callback telat). */
    private function canBeCredited(Deposit $deposit): bool
    {
        return in_array($deposit->status, [Deposit::STATUS_PENDING, Deposit::STATUS_EXPIRED], true);
    }

    private function clientOrNull(): ?DuitkuClient
    {
        try {
            return DuitkuClient::make(forCallback: true);
        } catch (RuntimeException) {
            return null;
        }
    }

    private function uniqueReference(): string
    {
        do {
            $reference = 'DEP'.now()->format('ymdHis').Str::upper(Str::random(4));
        } while (Deposit::where('reference_number', $reference)->exists());

        return $reference;
    }
}

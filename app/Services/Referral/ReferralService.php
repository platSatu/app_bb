<?php

namespace App\Services\Referral;

use App\Models\Deposit;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\ReferralSetting;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class ReferralService
{
    // Tanpa huruf/angka yang mirip (O/0, I/1/L) supaya tidak salah ketik.
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    private const LENGTH = 8;

    public function __construct(private readonly WalletService $wallets)
    {
    }

    /** Kode referral milik user, dibuat otomatis kalau belum ada. */
    public function codeFor(User $user): ReferralCode
    {
        if ($existing = $user->referralCode) {
            return $existing;
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $code = new ReferralCode();
                $code->forceFill(['user_id' => $user->getKey(), 'code' => $this->randomCode()])->save();

                return $user->setRelation('referralCode', $code)->referralCode;
            } catch (QueryException $e) {
                // Bentrok unique: user sudah punya kode (request lain) atau kodenya kembar.
                if ($existing = ReferralCode::where('user_id', $user->getKey())->first()) {
                    return $user->setRelation('referralCode', $existing)->referralCode;
                }
            }
        }

        throw new \RuntimeException('Kode referral gagal dibuat.');
    }

    public function findActiveCode(?string $code): ?ReferralCode
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '' || strlen($code) > 12) {
            return null;
        }

        $referralCode = ReferralCode::with('user:id,name,status')->where('code', $code)->first();

        return $referralCode?->user?->isActive() ? $referralCode : null;
    }

    /** Catat siapa yang mengajak user baru ini. Aman dipanggil dua kali. */
    public function recordSignup(User $newUser, ?string $code): ?Referral
    {
        $referralCode = $this->findActiveCode($code);

        if (! $referralCode || $referralCode->user_id === $newUser->getKey()) {
            return null;
        }

        $referral = new Referral();
        $referral->forceFill([
            'referrer_id' => $referralCode->user_id,
            'referred_user_id' => $newUser->getKey(),
            'code' => $referralCode->code,
            'status' => Referral::STATUS_PENDING,
        ]);

        try {
            $referral->save();
        } catch (QueryException) {
            return Referral::where('referred_user_id', $newUser->getKey())->first();
        }

        return $referral;
    }

    /**
     * Cairkan bonus ke pengajak saat top up pertama yang berhasil dari orang
     * yang diajak. Aman dipanggil berulang: referral dikunci & hanya diproses
     * selama masih pending, dan kredit saldo memakai idempotency key unik.
     */
    public function rewardForDeposit(Deposit $deposit): ?Referral
    {
        $setting = ReferralSetting::current();

        if (! $setting->isPayable() || $deposit->amount < $setting->min_deposit_amount) {
            return null;
        }

        return DB::transaction(function () use ($deposit, $setting) {
            $referral = Referral::with(['referrer', 'referredUser'])
                ->where('referred_user_id', $deposit->user_id)
                ->where('status', Referral::STATUS_PENDING)
                ->lockForUpdate()
                ->first();

            if (! $referral || ! $referral->referrer?->isActive()) {
                return null;
            }

            $this->wallets->credit(
                $referral->referrer,
                $setting->reward_amount,
                WalletTransaction::TYPE_REFERRAL_BONUS,
                'referral:'.$referral->getKey(),
                $referral,
                'Bonus referral dari '.$referral->referredUser?->name,
            );

            $referral->forceFill([
                'status' => Referral::STATUS_REWARDED,
                'reward_amount' => $setting->reward_amount,
                'rewarded_at' => now(),
            ])->save();

            return $referral;
        });
    }

    private function randomCode(): string
    {
        $code = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }
}

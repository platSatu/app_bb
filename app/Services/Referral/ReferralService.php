<?php

namespace App\Services\Referral;

use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\ReferralCommission;
use App\Models\ReferralSetting;
use App\Models\User;
use App\Models\Voucher;
use App\Models\WalletTransaction;
use App\Services\Wallet\WalletService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Referral = komisi dari pembelian paket (bukan dari top up, supaya tidak bisa "diternak" lalu ditarik).
 * - Pengajak A punya persen P (default di pengaturan, bisa diatur superadmin per user).
 * - A boleh memberikan sebagian (S <= P) ke orang yang diajak B sebagai potongan harga; A sendiri dapat P - S.
 * - Berlaku untuk semua pembelian paket B selama active_months sejak B daftar.
 * - Komisi ditahan hold_days (bisa dibatalkan superadmin), lalu cair ke saldo A lewat releaseDue().
 * Persen & nominal disalin ke referral_commissions saat beli; pembulatan selalu ke bawah (tidak pernah lebih dari haknya).
 */
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

                return $user->setRelation('referralCode', $code->refresh())->referralCode;
            } catch (QueryException $e) {
                // Bentrok unique: user sudah punya kode (request lain) atau kodenya kembar.
                if ($existing = ReferralCode::where('user_id', $user->getKey())->first()) {
                    return $user->setRelation('referralCode', $existing)->referralCode;
                }
            }
        }

        throw new RuntimeException('Kode referral gagal dibuat.');
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
     * Hitungan referral untuk pembelian $buyer seharga $price, atau null kalau tidak ada referral yang berlaku.
     *
     * @return array{referral: Referral, percent: int, share: int, price: int, discount: int, paid: int, amount: int}|null
     */
    public function terms(User $buyer, int $price): ?array
    {
        $setting = ReferralSetting::current();

        if (! $setting->is_active || $price <= 0) {
            return null;
        }

        $referral = Referral::with('referrer.referralCode')
            ->where('referred_user_id', $buyer->getKey())
            ->where('created_at', '>=', now()->subMonths($setting->active_months))
            ->first();
        $code = $referral?->referrer?->referralCode;

        if (! $code || ! $referral->referrer->isActive() || $referral->referrer_id === $buyer->getKey()) {
            return null;
        }

        $percent = $code->commissionPercent($setting);
        $share = $code->sharePercent($setting);
        $discount = intdiv($price * $share, 100);
        $amount = intdiv($price * ($percent - $share), 100);

        if ($discount + $amount === 0) {
            return null;
        }

        return ['referral' => $referral, 'percent' => $percent, 'share' => $share, 'price' => $price, 'discount' => $discount, 'paid' => $price - $discount, 'amount' => $amount];
    }

    /** Catat komisi satu pembelian. Dipanggil di DALAM transaksi pembelian; satu voucher = satu komisi (unique). */
    public function record(Voucher $voucher, User $buyer, string $packageName, array $terms): ReferralCommission
    {
        $hasAmount = $terms['amount'] > 0;

        $commission = new ReferralCommission();
        $commission->forceFill([
            'referral_id' => $terms['referral']->getKey(),
            'referrer_id' => $terms['referral']->referrer_id,
            'buyer_id' => $buyer->getKey(),
            'voucher_id' => $voucher->getKey(),
            'package_name' => mb_substr($packageName, 0, 150),
            'price' => $terms['price'],
            'discount_amount' => $terms['discount'],
            'paid_amount' => $terms['paid'],
            'commission_percent' => $terms['percent'],
            'share_percent' => $terms['share'],
            'amount' => $terms['amount'],
            // Semua bagian diberikan ke teman (komisi 0): tidak ada yang perlu ditahan.
            'status' => $hasAmount ? ReferralCommission::STATUS_HELD : ReferralCommission::STATUS_RELEASED,
            'release_at' => now()->addDays(ReferralSetting::current()->hold_days),
            'released_at' => $hasAmount ? null : now(),
        ])->save();

        return $commission;
    }

    /** Pengajak mengatur bagian komisinya untuk orang yang diajak (0 = tidak ada potongan untuk mereka). */
    public function setShare(User $user, int $percent): ReferralCode
    {
        $code = $this->codeFor($user);
        $max = $code->commissionPercent(ReferralSetting::current());

        if ($percent < 0 || $percent > $max) {
            throw new RuntimeException("Bagian untuk teman harus antara 0% dan {$max}% (komisi Anda).");
        }

        $code->forceFill(['share_percent' => $percent])->save();

        return $code;
    }

    /** Superadmin mengatur persen komisi satu user; null = ikut persen default. */
    public function setRate(User $user, ?int $percent): ReferralCode
    {
        $code = $this->codeFor($user);
        $code->commission_percent = $percent;
        // Bagian untuk teman tidak boleh melebihi persen yang baru.
        $code->share_percent = min((int) $code->share_percent, $code->commissionPercent(ReferralSetting::current()));
        $code->save();

        return $code;
    }

    /** Cairkan semua komisi yang masa tahannya sudah lewat. @return int jumlah yang cair */
    public function releaseDue(): int
    {
        $released = 0;

        ReferralCommission::where('status', ReferralCommission::STATUS_HELD)->where('release_at', '<=', now())
            ->select('id')->chunkById(200, function ($rows) use (&$released) {
                foreach ($rows as $row) {
                    $released += (int) $this->release($row->getKey());
                }
            });

        return $released;
    }

    /**
     * Cairkan satu komisi ke saldo pengajak. Baris dikunci & dicek ulang di dalam transaksi, kredit memakai
     * idempotency key, jadi dipanggil berulang / bersamaan tetap hanya cair sekali. Pengajak yang akunnya
     * sedang nonaktif dilewati (tetap ditahan) sampai aktif lagi atau dibatalkan superadmin.
     */
    public function release(string $id): bool
    {
        return DB::transaction(function () use ($id) {
            $commission = ReferralCommission::with('referrer')->whereKey($id)->lockForUpdate()->first();

            if (! $commission || $commission->status !== ReferralCommission::STATUS_HELD
                || $commission->release_at->isFuture() || ! $commission->referrer?->isActive()) {
                return false;
            }

            $this->wallets->credit(
                $commission->referrer,
                $commission->amount,
                WalletTransaction::TYPE_REFERRAL_BONUS,
                'referral-commission:'.$commission->getKey(),
                $commission,
                "Komisi referral {$commission->commission_percent}% - {$commission->package_name}",
            );

            $commission->forceFill(['status' => ReferralCommission::STATUS_RELEASED, 'released_at' => now()])->save();

            return true;
        });
    }

    /** Superadmin membatalkan komisi yang masih ditahan (komplain / refund). Yang sudah cair tidak bisa dibatalkan. */
    public function cancel(ReferralCommission $commission, User $admin, string $reason): ReferralCommission
    {
        return DB::transaction(function () use ($commission, $admin, $reason) {
            $locked = ReferralCommission::whereKey($commission->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== ReferralCommission::STATUS_HELD) {
                throw new RuntimeException('Komisi ini sudah '.strtolower($locked->statusLabel()).', tidak bisa dibatalkan.');
            }

            $locked->forceFill([
                'status' => ReferralCommission::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $admin->getKey(),
                'cancel_reason' => mb_substr(trim($reason), 0, 255),
            ])->save();

            return $locked;
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

<?php

namespace App\Services\Subscription;

use App\Models\BranchSubscription;
use App\Models\Package;
use App\Models\SubscriptionHistory;
use App\Models\TrialClaim;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\Voucher;
use App\Models\WalletTransaction;
use App\Services\Referral\ReferralService;
use App\Services\Wallet\InsufficientBalanceException;
use App\Services\Wallet\WalletService;
use App\Support\Apps;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Beli paket (potong saldo) -> voucher -> redeem ke satu branch -> masa aktif tiap layanan bertambah.
 * Pembeli yang diajak lewat referral bisa dapat potongan harga, dan pengajaknya dapat komisi (lihat ReferralService).
 * Masa aktif bertumpuk: dihitung dari tanggal berakhir lama kalau masih aktif, dari sekarang kalau sudah habis.
 * Semua perubahan memakai transaksi + lockForUpdate supaya klik ganda tidak memotong saldo/menambah hari dua kali.
 */
class SubscriptionService
{
    /** Kode voucher yang dibeli untuk diberikan ke orang lain berlaku selama ini sebelum dipakai. */
    public const VOUCHER_VALID_DAYS = 90;

    public function __construct(private readonly WalletService $wallets, private readonly ReferralService $referrals)
    {
    }

    /** $branch diisi = langsung aktif di branch itu; null = disimpan sebagai kode voucher. */
    public function purchase(User $buyer, Package $package, ?UserBranch $branch): Voucher
    {
        if (! $package->isActive() || empty($package->apps)) {
            throw new RuntimeException('Paket ini sedang tidak dijual.');
        }

        if ($package->is_trial && ! $branch) {
            throw new RuntimeException('Paket trial harus langsung diaktifkan ke branch, tidak bisa disimpan sebagai voucher.');
        }

        try {
            return DB::transaction(function () use ($buyer, $package, $branch) {
                if ($package->is_trial) {
                    $this->claimTrial($buyer, $package, $branch);
                }

                // Harga final dihitung di dalam transaksi yang sama dengan potong saldo & catat komisi.
                $referral = $this->referrals->terms($buyer, (int) $package->price);
                $price = $referral['paid'] ?? (int) $package->price;

                $voucher = new Voucher();
                $voucher->forceFill([
                    'package_id' => $package->getKey(),
                    'user_id' => $buyer->getKey(),
                    'code' => $this->newCode(),
                    'price' => $price,
                    'valid_from' => now(),
                    'valid_until' => now()->addDays(self::VOUCHER_VALID_DAYS),
                ])->save();

                if ($price > 0) {
                    $this->wallets->debit($buyer, $price, WalletTransaction::TYPE_PURCHASE, 'package:'.$voucher->getKey(), $voucher, 'Beli paket '.$package->name);
                }

                if ($referral) {
                    $this->referrals->record($voucher, $buyer, $package->name, $referral);
                }

                if ($branch) {
                    $this->redeem($voucher, $branch, $buyer, $package->is_trial ? SubscriptionHistory::SOURCE_TRIAL : SubscriptionHistory::SOURCE_PURCHASE);
                }

                return $voucher;
            });
        } catch (InsufficientBalanceException) {
            throw new RuntimeException('Saldo Anda belum cukup untuk paket ini. Silakan tambah saldo dulu.');
        }
    }

    /** Pakai voucher untuk satu branch. Bisa dipanggil di dalam transaksi purchase(). */
    public function redeem(Voucher $voucher, UserBranch $branch, User $actor, string $source = SubscriptionHistory::SOURCE_VOUCHER): void
    {
        DB::transaction(function () use ($voucher, $branch, $actor, $source) {
            $locked = Voucher::with('package')->whereKey($voucher->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->redeemed_at) {
                throw new RuntimeException('Kode voucher ini sudah pernah dipakai.');
            }

            if ($locked->valid_until->isPast()) {
                throw new RuntimeException('Kode voucher ini sudah kedaluwarsa.');
            }

            $package = $locked->package;

            if (! $package || empty($package->apps)) {
                throw new RuntimeException('Paket untuk voucher ini belum punya layanan. Hubungi admin kami.');
            }

            if ($package->is_trial && $locked->user_id !== $actor->getKey()) {
                throw new RuntimeException('Voucher trial hanya bisa dipakai oleh pemiliknya.');
            }

            $locked->forceFill(['redeemed_by' => $actor->getKey(), 'redeemed_at' => now()])->save();

            foreach ($package->apps as $appKey) {
                $this->extend($branch, $appKey, $package->duration, $source, $actor, $package->getKey(), $locked->getKey());
            }
        });
    }

    /** Tambah (atau kurangi, $days negatif) masa aktif satu layanan di satu branch. */
    public function extend(UserBranch $branch, string $appKey, int $days, string $source, ?User $actor, ?string $packageId = null, ?string $voucherId = null, ?string $note = null): BranchSubscription
    {
        if (! array_key_exists($appKey, Apps::LIST)) {
            throw new RuntimeException('Layanan tidak dikenal.');
        }

        return DB::transaction(function () use ($branch, $appKey, $days, $source, $actor, $packageId, $voucherId, $note) {
            // Kunci branch dulu supaya baris langganan pertama tidak dibuat dua kali bersamaan.
            UserBranch::whereKey($branch->getKey())->lockForUpdate()->firstOrFail();

            $subscription = BranchSubscription::where('branch_id', $branch->getKey())->where('app_key', $appKey)->lockForUpdate()->first();
            $previous = $subscription?->ends_at;
            $base = $previous && $previous->isFuture() ? $previous->copy() : now();
            $ends = $base->addDays($days);

            if (! $subscription) {
                $subscription = new BranchSubscription();
                $subscription->forceFill(['branch_id' => $branch->getKey(), 'app_key' => $appKey]);
            }

            $subscription->forceFill([
                // Mulai baru dihitung ulang kalau sebelumnya sudah habis (siklus baru).
                'starts_at' => $previous && $previous->isFuture() ? $subscription->starts_at : now(),
                'ends_at' => $ends,
            ]);

            // Batas paket ikut disalin. Diperpanjang sebelum habis = ambil yang lebih longgar (tidak turun kelas);
            // siklus baru / langganan lama tanpa batas tercatat = batas paket ini. Tambah hari manual tidak mengubah batas.
            if ($packageId && ($package = Package::find($packageId))) {
                $incoming = $package->limitsFor($appKey);
                $subscription->limits = $previous?->isFuture() && $subscription->limits !== null
                    ? PlanLimits::looser($subscription->limits, $incoming)
                    : $incoming;
            }

            $subscription->save();

            $history = new SubscriptionHistory();
            $history->forceFill([
                'branch_id' => $branch->getKey(),
                'app_key' => $appKey,
                'package_id' => $packageId,
                'voucher_id' => $voucherId,
                'source' => $source,
                'days' => $days,
                'previous_ends_at' => $previous,
                'new_ends_at' => $ends,
                'actor_id' => $actor?->getKey(),
                'note' => $note,
            ])->save();

            return $subscription;
        });
    }

    public function hasUsedTrial(User $user): bool
    {
        return TrialClaim::where('email', strtolower($user->email))
            ->when($user->handphone, fn ($q, $phone) => $q->orWhere('phone', $phone))
            ->exists();
    }

    private function claimTrial(User $user, Package $package, UserBranch $branch): void
    {
        if ($this->hasUsedTrial($user)) {
            throw new RuntimeException('Trial sudah pernah dipakai dengan nomor HP atau email ini.');
        }

        try {
            $claim = new TrialClaim();
            $claim->forceFill([
                'phone' => $user->handphone ?: null,
                'email' => strtolower($user->email),
                'user_id' => $user->getKey(),
                'branch_id' => $branch->getKey(),
                'package_id' => $package->getKey(),
            ])->save();
        } catch (QueryException) {
            // Unique phone/email: dua klik bersamaan, yang kedua ditolak.
            throw new RuntimeException('Trial sudah pernah dipakai dengan nomor HP atau email ini.');
        }
    }

    private function newCode(): string
    {
        do {
            $code = 'BZ'.strtoupper(Str::random(10));
        } while (Voucher::where('code', $code)->exists());

        return $code;
    }
}

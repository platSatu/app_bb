<?php

namespace App\Services\Wallet;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Satu-satunya pintu untuk mengubah saldo. Setiap mutasi:
 * - mengunci baris wallet (lockForUpdate) supaya request bersamaan antre,
 * - mencatat saldo sebelum & sesudah di wallet_transactions,
 * - memakai idempotency key unik supaya proses yang terulang (mis. callback
 *   payment gateway dikirim 2x) tidak menambah/memotong saldo 2 kali.
 * Uang dari Duitku bisa punya available_at (belum settlement): masuk saldo, tapi belum boleh
 * ditarik / dikirim keluar sampai tanggal itu (lihat assertSettled).
 */
class WalletService
{
    public function walletFor(User $user): Wallet
    {
        if ($wallet = Wallet::where('user_id', $user->getKey())->first()) {
            return $wallet;
        }

        try {
            $wallet = new Wallet();
            $wallet->forceFill(['user_id' => $user->getKey(), 'balance' => 0])->save();

            return $wallet;
        } catch (QueryException) {
            return Wallet::where('user_id', $user->getKey())->firstOrFail();
        }
    }

    public function balanceOf(User $user): int
    {
        return (int) Wallet::where('user_id', $user->getKey())->value('balance');
    }

    /** Ringkasan untuk tampilan: saldo, yang bisa ditarik saat ini, dan yang masih menunggu settlement. */
    public function summary(User $user): array
    {
        $wallet = Wallet::where('user_id', $user->getKey())->first();
        $balance = (int) $wallet?->balance;
        $pending = $wallet ? $this->pendingOf($wallet) : 0;

        return ['balance' => $balance, 'available' => max(0, $balance - $pending), 'pending' => min($balance, $pending)];
    }

    /** Uang masuk yang belum settlement, untuk penjelasan ke owner. */
    public function pendingItems(User $user, int $limit = 10)
    {
        return WalletTransaction::where('user_id', $user->getKey())->where('direction', WalletTransaction::CREDIT)
            ->where('available_at', '>', now())->orderBy('available_at')->limit($limit)
            ->get(['amount', 'payment_method', 'available_at', 'type']);
    }

    /**
     * Pastikan saldo yang SUDAH settlement cukup untuk uang keluar (tarik saldo / disbursement).
     * Wajib dipanggil di dalam DB::transaction sebelum debit: baris wallet dikunci di sini dan
     * tetap terkunci sampai transaksi selesai, jadi request lain antre dan tidak bisa memakai saldo yang sama.
     *
     * @throws InsufficientBalanceException
     */
    public function assertSettled(User $user, int $amount): void
    {
        if (DB::transactionLevel() === 0) {
            throw new RuntimeException('assertSettled harus dipanggil di dalam DB::transaction.');
        }

        $this->walletFor($user);
        $wallet = Wallet::where('user_id', $user->getKey())->lockForUpdate()->firstOrFail();
        $available = max(0, $wallet->balance - $this->pendingOf($wallet));

        if ($available < $amount) {
            throw new InsufficientBalanceException($wallet->balance, $amount, $available);
        }
    }

    public function credit(User $user, int $amount, string $type, string $idempotencyKey, ?Model $reference = null, ?string $description = null, ?User $actor = null, ?string $paymentMethod = null, ?CarbonInterface $availableAt = null): WalletTransaction
    {
        return $this->mutate($user, WalletTransaction::CREDIT, $amount, $type, $idempotencyKey, $reference, $description, $actor, $paymentMethod, $availableAt);
    }

    /** @throws InsufficientBalanceException */
    public function debit(User $user, int $amount, string $type, string $idempotencyKey, ?Model $reference = null, ?string $description = null, ?User $actor = null): WalletTransaction
    {
        return $this->mutate($user, WalletTransaction::DEBIT, $amount, $type, $idempotencyKey, $reference, $description, $actor);
    }

    private function pendingOf(Wallet $wallet): int
    {
        return (int) WalletTransaction::where('wallet_id', $wallet->getKey())->where('direction', WalletTransaction::CREDIT)
            ->where('available_at', '>', now())->sum('amount');
    }

    private function mutate(User $user, string $direction, int $amount, string $type, string $idempotencyKey, ?Model $reference, ?string $description, ?User $actor, ?string $paymentMethod = null, ?CarbonInterface $availableAt = null): WalletTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Nominal harus lebih dari 0.');
        }

        $this->walletFor($user);

        return DB::transaction(function () use ($user, $direction, $amount, $type, $idempotencyKey, $reference, $description, $actor, $paymentMethod, $availableAt) {
            $wallet = Wallet::where('user_id', $user->getKey())->lockForUpdate()->firstOrFail();

            if ($existing = WalletTransaction::where('idempotency_key', $idempotencyKey)->first()) {
                if ($existing->wallet_id !== $wallet->getKey()) {
                    throw new RuntimeException('Idempotency key sudah dipakai wallet lain.');
                }

                return $existing;
            }

            $before = $wallet->balance;

            if ($direction === WalletTransaction::DEBIT && $before < $amount) {
                throw new InsufficientBalanceException($before, $amount);
            }

            $after = $direction === WalletTransaction::CREDIT ? $before + $amount : $before - $amount;

            $transaction = new WalletTransaction();
            $transaction->forceFill([
                'wallet_id' => $wallet->getKey(),
                'user_id' => $user->getKey(),
                'type' => $type,
                'direction' => $direction,
                'amount' => $amount,
                'balance_before' => $before,
                'balance_after' => $after,
                'reference_type' => $reference ? $reference->getMorphClass() : null,
                'reference_id' => $reference?->getKey(),
                'description' => $description,
                'payment_method' => $paymentMethod ? strtoupper(substr($paymentMethod, 0, 20)) : null,
                'available_at' => $direction === WalletTransaction::CREDIT ? $availableAt : null,
                'idempotency_key' => $idempotencyKey,
                'created_by' => $actor?->getKey(),
            ])->save();

            $wallet->forceFill(['balance' => $after])->save();

            return $transaction;
        });
    }
}

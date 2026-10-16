<?php

namespace App\Services\Wallet;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
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

    public function credit(User $user, int $amount, string $type, string $idempotencyKey, ?Model $reference = null, ?string $description = null, ?User $actor = null): WalletTransaction
    {
        return $this->mutate($user, WalletTransaction::CREDIT, $amount, $type, $idempotencyKey, $reference, $description, $actor);
    }

    /** @throws InsufficientBalanceException */
    public function debit(User $user, int $amount, string $type, string $idempotencyKey, ?Model $reference = null, ?string $description = null, ?User $actor = null): WalletTransaction
    {
        return $this->mutate($user, WalletTransaction::DEBIT, $amount, $type, $idempotencyKey, $reference, $description, $actor);
    }

    private function mutate(User $user, string $direction, int $amount, string $type, string $idempotencyKey, ?Model $reference, ?string $description, ?User $actor): WalletTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Nominal harus lebih dari 0.');
        }

        $this->walletFor($user);

        return DB::transaction(function () use ($user, $direction, $amount, $type, $idempotencyKey, $reference, $description, $actor) {
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
                'idempotency_key' => $idempotencyKey,
                'created_by' => $actor?->getKey(),
            ])->save();

            $wallet->forceFill(['balance' => $after])->save();

            return $transaction;
        });
    }
}

<?php

namespace App\Services\Wallet;

use RuntimeException;

class InsufficientBalanceException extends RuntimeException
{
    /** $available: saldo yang sudah settlement (null = tidak dicek). */
    public function __construct(public readonly int $balance, public readonly int $required, public readonly ?int $available = null)
    {
        parent::__construct($available !== null && $available < $balance
            ? 'Sebagian saldo masih dalam proses pencairan, jadi belum cukup untuk transaksi ini.'
            : 'Saldo Anda belum cukup untuk transaksi ini.');
    }

    /** Yang benar-benar bisa dipakai untuk uang keluar. */
    public function usable(): int
    {
        return $this->available ?? $this->balance;
    }
}

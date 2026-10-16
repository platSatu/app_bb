<?php

namespace App\Services\Wallet;

use RuntimeException;

class InsufficientBalanceException extends RuntimeException
{
    public function __construct(public readonly int $balance, public readonly int $required)
    {
        parent::__construct('Saldo Anda belum cukup untuk transaksi ini.');
    }
}

<?php

namespace App\Services\Social;

use RuntimeException;

/**
 * Kesalahan dari platform sosmed, pesannya aman ditampilkan ke superadmin (tanpa token / isi balasan mentah).
 * $expired = token ditolak: akun ditandai "perlu disambungkan ulang".
 */
class SocialException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $expired = false)
    {
        parent::__construct(mb_substr($message, 0, 480));
    }
}

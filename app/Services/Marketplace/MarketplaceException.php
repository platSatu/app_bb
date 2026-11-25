<?php

namespace App\Services\Marketplace;

use RuntimeException;

/** Error dari API marketplace, pesannya aman ditampilkan ke owner. */
class MarketplaceException extends RuntimeException
{
    public function __construct(string $message, private readonly ?string $apiCode = null, private readonly array $context = [])
    {
        parent::__construct($message);
    }

    public function apiCode(): ?string
    {
        return $this->apiCode;
    }

    public function context(): array
    {
        return $this->context;
    }

    /** Token toko tidak berlaku lagi (seller perlu otorisasi ulang). */
    public function isAuthError(): bool
    {
        return in_array($this->apiCode, [
            // Lazada
            'IllegalAccessToken', 'AccessTokenExpired', 'IllegalRefreshToken', 'RefreshTokenExpired',
            // Shopee (ejaan "acceess" memang begitu dari Shopee)
            'invalid_access_token', 'invalid_acceess_token', 'error_invalid_access_token', 'invalid_refresh_token',
        ], true);
    }
}

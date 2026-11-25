<?php

namespace App\Http\Controllers\Marketplace\Tiktok;

use App\Http\Controllers\Marketplace\StoreAuthController;

/** Sambungkan / perpanjang / putus toko TikTok Shop. Alurnya di StoreAuthController. */
class TiktokAuthController extends StoreAuthController
{
    protected function channelKey(): string
    {
        return 'tiktok';
    }
}

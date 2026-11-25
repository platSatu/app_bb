<?php

namespace App\Http\Controllers\Marketplace\Tiktok;

use App\Http\Controllers\Marketplace\StoreProductController;

/** Produk di toko TikTok Shop (tarik & lihat). Alurnya di StoreProductController. */
class TiktokProductController extends StoreProductController
{
    protected function channelKey(): string
    {
        return 'tiktok';
    }
}

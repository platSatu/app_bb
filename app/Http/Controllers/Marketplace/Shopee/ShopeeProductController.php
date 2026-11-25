<?php

namespace App\Http\Controllers\Marketplace\Shopee;

use App\Http\Controllers\Marketplace\StoreProductController;

/** Produk di toko Shopee (tarik & lihat). Alurnya di StoreProductController. */
class ShopeeProductController extends StoreProductController
{
    protected function channelKey(): string
    {
        return 'shopee';
    }
}

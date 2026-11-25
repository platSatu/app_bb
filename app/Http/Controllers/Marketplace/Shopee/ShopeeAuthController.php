<?php

namespace App\Http\Controllers\Marketplace\Shopee;

use App\Http\Controllers\Marketplace\StoreAuthController;

/** Sambungkan / perpanjang / putus toko Shopee. Alurnya di StoreAuthController. */
class ShopeeAuthController extends StoreAuthController
{
    protected function channelKey(): string
    {
        return 'shopee';
    }
}

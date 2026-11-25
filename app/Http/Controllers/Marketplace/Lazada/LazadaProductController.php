<?php

namespace App\Http\Controllers\Marketplace\Lazada;

use App\Http\Controllers\Marketplace\StoreProductController;

/** Produk di toko Lazada (tarik & lihat). Alurnya di StoreProductController. */
class LazadaProductController extends StoreProductController
{
    protected function channelKey(): string
    {
        return 'lazada';
    }
}

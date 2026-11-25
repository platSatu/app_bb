<?php

namespace App\Http\Controllers\Marketplace\Lazada;

use App\Http\Controllers\Marketplace\StoreAuthController;

/** Sambungkan / perpanjang / putus toko Lazada. Alurnya di StoreAuthController. */
class LazadaAuthController extends StoreAuthController
{
    protected function channelKey(): string
    {
        return 'lazada';
    }
}

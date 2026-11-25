<?php

/*
| Channel marketplace yang bisa disambungkan dari tab Marketplace di Catalog Online.
| Channel baru (mis. TikTok Shop) = tambah driver yang mengimplementasikan
| App\Services\Marketplace\Contracts\MarketplaceChannel (+ PublishesProducts), controller turunan
| App\Http\Controllers\Marketplace\Store*Controller, daftarkan di routes/web/marketplace.php, lalu set 'enabled' => true.
*/

return [
    'channels' => [
        'lazada' => [
            'label' => 'Lazada',
            'icon' => 'ri-shopping-bag-line',
            'color' => '#0f146d',
            'enabled' => true,
            'driver' => \App\Services\Marketplace\Lazada\LazadaChannel::class,
        ],
        'tiktok' => [
            'label' => 'TikTok Shop',
            'icon' => 'ri-tiktok-line',
            'color' => '#000000',
            'enabled' => true,
            'driver' => \App\Services\Marketplace\Tiktok\TiktokChannel::class,
        ],
        'shopee' => [
            'label' => 'Shopee',
            'icon' => 'ri-store-3-line',
            'color' => '#ee4d2d',
            'enabled' => true,
            'driver' => \App\Services\Marketplace\Shopee\ShopeeChannel::class,
        ],
    ],

    // Batas tarik produk sekali sinkron (per toko), supaya request tidak timeout.
    'sync_max_products' => (int) env('MARKETPLACE_SYNC_MAX_PRODUCTS', 1000),
];

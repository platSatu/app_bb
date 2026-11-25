<?php

/*
| Channel marketplace yang bisa disambungkan dari tab Marketplace di Catalog Online.
| Channel baru (TikTok Shop, Shopee) = tambah driver yang mengimplementasikan
| App\Services\Marketplace\Contracts\MarketplaceChannel, lalu set 'enabled' => true.
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
            'enabled' => false,
            'driver' => null,
        ],
        'shopee' => [
            'label' => 'Shopee',
            'icon' => 'ri-store-3-line',
            'color' => '#ee4d2d',
            'enabled' => false,
            'driver' => null,
        ],
    ],

    // Batas tarik produk sekali sinkron (per toko), supaya request tidak timeout.
    'sync_max_products' => (int) env('MARKETPLACE_SYNC_MAX_PRODUCTS', 1000),
];

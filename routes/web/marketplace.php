<?php

use App\Http\Controllers\Marketplace\Lazada\LazadaAuthController;
use App\Http\Controllers\Marketplace\Lazada\LazadaProductController;
use App\Http\Controllers\Marketplace\Lazada\LazadaPublishController;
use App\Http\Controllers\Marketplace\MarketplaceController;
use App\Http\Controllers\Marketplace\Shopee\ShopeeAuthController;
use App\Http\Controllers\Marketplace\Shopee\ShopeeProductController;
use App\Http\Controllers\Marketplace\Shopee\ShopeePublishController;
use App\Http\Controllers\Superadmin\Marketplace\MarketplaceAppController;
use Illuminate\Support\Facades\Route;

/*
| Marketplace = tab di page Catalog Online (/pages/{page}/marketplace).
| Setiap channel punya route standar yang sama (lihat $channelRoutes), dengan nama:
|   pages.marketplace.{channel}.connect | refresh | disconnect
|   pages.marketplace.{channel}.products.index | products.sync
|   pages.marketplace.{channel}.publish.index | publish.create | publish.store
|   pages.marketplace.{channel}.categories | categories.suggest | categories.attributes
| ditambah callback OAuth: marketplace.{channel}.callback.
| Channel baru: tambah satu baris $channelRoutes(...) + callback.
*/

$channelRoutes = function (string $channel, string $auth, string $products, string $publish, ?callable $extra = null) {
    Route::prefix('{page}/marketplace/'.$channel)->name('marketplace.'.$channel.'.')->group(function () use ($auth, $products, $publish, $extra) {
        Route::post('connect', [$auth, 'connect'])->middleware('throttle:10,1')->name('connect');

        Route::prefix('{store}')->whereUuid('store')->group(function () use ($auth, $products, $publish, $extra) {
            Route::post('refresh', [$auth, 'refresh'])->middleware('throttle:10,1')->name('refresh');
            Route::delete('/', [$auth, 'disconnect'])->name('disconnect');

            Route::get('products', [$products, 'index'])->name('products.index');
            Route::post('products/sync', [$products, 'sync'])->middleware('throttle:6,1')->name('products.sync');

            Route::controller($publish)->group(function () use ($extra) {
                Route::get('publish', 'index')->name('publish.index');
                Route::get('publish/{product}', 'create')->whereUuid('product')->name('publish.create');
                Route::post('publish/{product}', 'store')->whereUuid('product')->middleware('throttle:20,1')->name('publish.store');
                Route::get('categories', 'categories')->middleware('throttle:60,1')->name('categories');
                Route::get('categories/suggest', 'suggest')->middleware('throttle:30,1')->name('categories.suggest');
                Route::get('categories/{category}/attributes', 'attributes')->middleware('throttle:60,1')->name('categories.attributes');

                if ($extra) {
                    $extra();
                }
            });
        });
    });
};

// Owner (area Pages).
Route::middleware(['auth', 'permission:pages.list'])->prefix('pages')->name('pages.')->group(function () use ($channelRoutes) {
    Route::get('{page}/marketplace', [MarketplaceController::class, 'index'])->name('marketplace.index');

    $channelRoutes('lazada', LazadaAuthController::class, LazadaProductController::class, LazadaPublishController::class);

    $channelRoutes('shopee', ShopeeAuthController::class, ShopeeProductController::class, ShopeePublishController::class, function () {
        Route::get('categories/{category}/brands', 'brands')->middleware('throttle:60,1')->name('categories.brands');
    });
});

// Callback OAuth: satu alamat per channel untuk semua page & app (page/app dibawa lewat state).
// Lazada: isi URL ini persis di Callback URL app (ISV Console). Shopee: isi domainnya di Redirect URL Domain (Console).
Route::middleware('auth')->group(function () {
    Route::get('marketplace/lazada/callback', [LazadaAuthController::class, 'callback'])->name('marketplace.lazada.callback');
    Route::get('marketplace/shopee/callback', [ShopeeAuthController::class, 'callback'])->name('marketplace.shopee.callback');
});

// Superadmin: tab Marketplace (lihat saja) di Applications › Pages.
Route::middleware(['auth', 'superadmin', 'permission:superadmin.pages,view'])->prefix('superadmin/pages')->name('superadmin.pages.')->group(function () {
    Route::get('{page}/marketplace', [MarketplaceController::class, 'index'])->name('marketplace.index');
});

// Superadmin › Settings › Marketplace Apps: kredensial app per channel, boleh beberapa akun.
Route::middleware(['auth', 'superadmin', 'permission:superadmin.marketplace-apps,manage'])
    ->prefix('superadmin/marketplace-apps')->name('superadmin.marketplace-apps.')
    ->controller(MarketplaceAppController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{app}/edit', 'edit')->whereUuid('app')->name('edit');
        Route::put('{app}', 'update')->whereUuid('app')->name('update');
        Route::delete('{app}', 'destroy')->whereUuid('app')->name('destroy');
    });

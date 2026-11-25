<?php

use App\Http\Controllers\Marketplace\Lazada\LazadaAuthController;
use App\Http\Controllers\Marketplace\Lazada\LazadaProductController;
use App\Http\Controllers\Marketplace\Lazada\LazadaPublishController;
use App\Http\Controllers\Marketplace\MarketplaceController;
use App\Http\Controllers\Superadmin\Marketplace\MarketplaceAppController;
use Illuminate\Support\Facades\Route;

/*
| Marketplace = tab di page Catalog Online (/pages/{page}/marketplace).
| Controller di App\Http\Controllers\Marketplace\{Channel}, view di resources/views/marketplace/{channel}.
| Channel baru (TikTok Shop, Shopee) cukup menambah grup route sejenis di bawah grup Lazada.
*/

// Owner (area Pages).
Route::middleware(['auth', 'permission:pages.list'])->prefix('pages')->name('pages.')->group(function () {
    Route::get('{page}/marketplace', [MarketplaceController::class, 'index'])->name('marketplace.index');

    Route::prefix('{page}/marketplace/lazada')->name('marketplace.lazada.')->group(function () {
        Route::post('connect', [LazadaAuthController::class, 'connect'])->middleware('throttle:10,1')->name('connect');
        Route::post('{store}/refresh', [LazadaAuthController::class, 'refresh'])->whereUuid('store')->middleware('throttle:10,1')->name('refresh');
        Route::delete('{store}', [LazadaAuthController::class, 'disconnect'])->whereUuid('store')->name('disconnect');

        Route::get('{store}/products', [LazadaProductController::class, 'index'])->whereUuid('store')->name('products.index');
        Route::post('{store}/products/sync', [LazadaProductController::class, 'sync'])->whereUuid('store')->middleware('throttle:6,1')->name('products.sync');

        Route::controller(LazadaPublishController::class)->prefix('{store}')->whereUuid('store')->group(function () {
            Route::get('publish', 'index')->name('publish.index');
            Route::get('publish/{product}', 'create')->whereUuid('product')->name('publish.create');
            Route::post('publish/{product}', 'store')->whereUuid('product')->middleware('throttle:20,1')->name('publish.store');
            Route::get('categories', 'categories')->middleware('throttle:60,1')->name('categories');
            Route::get('categories/suggest', 'suggest')->middleware('throttle:30,1')->name('categories.suggest');
            Route::get('categories/{category}/attributes', 'attributes')->middleware('throttle:60,1')->name('categories.attributes');
        });
    });
});

// Callback OAuth Lazada: satu alamat untuk semua page & app (page/app dibawa lewat state).
// Alamat ini yang diisi di Callback URL app pada Lazada ISV Console.
Route::middleware('auth')->get('marketplace/lazada/callback', [LazadaAuthController::class, 'callback'])->name('marketplace.lazada.callback');

// Superadmin: tab Marketplace (lihat saja) di Applications › Pages.
Route::middleware(['auth', 'superadmin', 'permission:superadmin.pages,view'])->prefix('superadmin/pages')->name('superadmin.pages.')->group(function () {
    Route::get('{page}/marketplace', [MarketplaceController::class, 'index'])->name('marketplace.index');
});

// Superadmin › Settings › Marketplace Apps: App Key/Secret per channel, boleh beberapa akun.
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

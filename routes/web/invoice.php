<?php

use App\Http\Controllers\Invoice\ApiClientController;
use App\Http\Controllers\Invoice\BatchController;
use App\Http\Controllers\Invoice\CategoryController;
use App\Http\Controllers\Invoice\DisbursementController;
use App\Http\Controllers\Invoice\DocsController;
use App\Http\Controllers\Invoice\InvoiceController;
use App\Http\Controllers\Invoice\PublicInvoiceController;
use App\Http\Controllers\Superadmin\Invoice\InvoiceSettingController;
use Illuminate\Support\Facades\Route;

// Halaman bayar publik (tanpa login). Token 48 karakter acak.
// Ikut tutup kalau paket Tagihan branch habis (invoice yang sudah lunas tetap bisa dibuka).
Route::controller(PublicInvoiceController::class)->middleware('public.app:invoices')->prefix('pay/{token}')->name('invoice.pay')->group(function () {
    Route::get('/', 'show')->middleware('throttle:60,1')->name('');
    Route::post('checkout', 'checkout')->middleware('throttle:10,1')->name('.checkout');
    Route::get('return', 'returned')->middleware('throttle:30,1')->name('.return');
    Route::get('status', 'status')->middleware('throttle:30,1')->name('.status');
});

// Owner. Data selalu dibatasi ke branch miliknya lewat accessibleBy().
Route::middleware('auth')->prefix('invoices')->name('invoices.')->group(function () {
    Route::middleware('permission:invoices.list')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::get('docs', DocsController::class)->name('docs');
        Route::get('{invoice}', [InvoiceController::class, 'show'])->whereUuid('invoice')->name('show');
        Route::post('{invoice}/cancel', [InvoiceController::class, 'cancel'])->whereUuid('invoice')->name('cancel');
        Route::post('{invoice}/resend', [InvoiceController::class, 'resend'])->whereUuid('invoice')->middleware('throttle:10,1')->name('resend');
        Route::post('{invoice}/regenerate', [InvoiceController::class, 'regenerate'])->whereUuid('invoice')->name('regenerate');
    });

    Route::middleware('permission:invoices.batches')->prefix('batches')->name('batches.')->controller(BatchController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
        Route::get('{batch}', 'show')->name('show');
        Route::post('{batch}/customers', 'addCustomers')->name('customers');
        Route::post('{batch}/copy', 'copy')->middleware('throttle:20,1')->name('copy');
        Route::post('{batch}/cancel', 'cancel')->name('cancel');
    });

    Route::middleware('permission:invoices.categories')->group(function () {
        Route::resource('categories', CategoryController::class)->except('show');
        Route::controller(CategoryController::class)->prefix('categories/{category}')->name('categories.')->group(function () {
            Route::get('customers', 'customers')->name('customers');
            Route::post('customers', 'subscribe')->name('subscribe');
            Route::post('customers/new', 'quickAdd')->middleware('throttle:60,1')->name('quick-add');
            Route::put('customers/{subscription}', 'updateSubscription')->name('subscriptions.update');
            Route::delete('customers/{subscription}', 'unsubscribe')->name('subscriptions.destroy');
        });
    });

    Route::middleware('permission:invoices.api')->prefix('api')->name('api.')->controller(ApiClientController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('{client}', 'update')->name('update');
        Route::post('{client}/regenerate', 'regenerate')->middleware('throttle:10,1')->name('regenerate');
        Route::post('{client}/test', 'test')->middleware('throttle:10,1')->name('test');
    });

    // Kirim dana dari saldo ke banyak rekening penerima (dashboard atau API). Tab: Riwayat, Rekening, Pengaturan.
    Route::middleware('permission:invoices.disbursements')->prefix('disbursements')->name('disbursements.')->controller(DisbursementController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'send')->middleware('throttle:20,1')->name('send');
        Route::post('{disbursement}/check', 'check')->whereUuid('disbursement')->middleware('throttle:20,1')->name('check');
        Route::get('recipients', 'recipients')->name('recipients');
        Route::post('recipients', 'storeRecipient')->middleware('throttle:20,1')->name('recipients.store');
        Route::post('recipients/{recipient}/toggle', 'toggleRecipient')->whereUuid('recipient')->name('recipients.toggle');
        Route::get('settings', 'settings')->name('settings');
        Route::put('settings/{client}', 'updateSettings')->whereUuid('client')->name('settings.update');
    });
});

// Superadmin: lihat semua invoice + atur biaya layanan.
Route::middleware(['auth', 'superadmin'])->prefix('superadmin/invoices')->name('superadmin.invoices.')->group(function () {
    Route::middleware('permission:superadmin.invoices,view')->group(function () {
        Route::get('/', [InvoiceController::class, 'index'])->name('index');
        Route::get('{invoice}', [InvoiceController::class, 'show'])->whereUuid('invoice')->name('show');
    });

    Route::middleware('permission:superadmin.invoice-settings,manage')->group(function () {
        Route::get('settings/fee', [InvoiceSettingController::class, 'edit'])->name('settings');
        Route::put('settings/fee', [InvoiceSettingController::class, 'update'])->name('settings.update');
    });
});

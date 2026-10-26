<?php

use App\Http\Controllers\Contact\CustomerController;
use Illuminate\Support\Facades\Route;

// Customers per aplikasi (satu tabel customers, dibedakan app_key). Aplikasinya dibaca dari nama route.
// Hak akses data dicek lagi di controller lewat Customer::accessibleBy() / branch milik owner.
$read = function () {
    Route::get('/', [CustomerController::class, 'index'])->name('index');
    Route::get('{customer}', [CustomerController::class, 'show'])->whereUuid('customer')->name('show');
};

$manage = function () use ($read) {
    Route::controller(CustomerController::class)->group(function () {
        Route::get('contoh-excel', 'template')->name('template');
        Route::post('/', 'store')->middleware('throttle:60,1')->name('store');
        Route::post('import', 'import')->middleware('throttle:10,1')->name('import');
        Route::put('{customer}', 'update')->whereUuid('customer')->name('update');
        Route::delete('{customer}', 'destroy')->whereUuid('customer')->name('destroy');
        Route::post('{customer}/akun', 'account')->whereUuid('customer')->middleware('throttle:20,1')->name('account');
    });
    $read();
};

Route::middleware(['auth', 'permission:pages.customers'])->prefix('pages/customers')->name('pages.customers.')->group($manage);
Route::middleware(['auth', 'permission:invoices.customers'])->prefix('invoices/customers')->name('invoices.customers.')->group($manage);

// Superadmin (Data User › Customer): semua aplikasi. Lihat & export; import ke branch mana pun butuh izin kelola.
Route::middleware(['auth', 'superadmin'])->prefix('superadmin/customers')->name('superadmin.contacts.')->group(function () use ($read) {
    Route::middleware('permission:superadmin.customers,view')->group(function () use ($read) {
        Route::get('export', [CustomerController::class, 'export'])->middleware('throttle:10,1')->name('export');
        $read();
    });
    Route::middleware('permission:superadmin.customers,manage')->controller(CustomerController::class)->group(function () {
        Route::get('contoh-excel', 'template')->name('template');
        Route::post('import', 'import')->middleware('throttle:10,1')->name('import');
        Route::delete('/', 'destroyMany')->middleware('throttle:20,1')->name('destroy-many');
    });
});

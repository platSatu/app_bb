<?php

use App\Http\Controllers\Contact\CustomerController;
use Illuminate\Support\Facades\Route;

// Customers per aplikasi (satu tabel customers, dibedakan app_key). Aplikasinya dibaca dari nama route.
// Hak akses data dicek lagi di controller lewat Customer::accessibleBy().
$routes = function () {
    Route::get('/', [CustomerController::class, 'index'])->name('index');
    Route::get('{customer}', [CustomerController::class, 'show'])->whereUuid('customer')->name('show');
};

Route::middleware(['auth', 'permission:pages.customers'])->prefix('pages/customers')->name('pages.customers.')->group($routes);
Route::middleware(['auth', 'permission:invoices.customers'])->prefix('invoices/customers')->name('invoices.customers.')->group($routes);

// Superadmin: semua aplikasi, bisa difilter.
Route::middleware(['auth', 'superadmin', 'permission:superadmin.customers,view'])
    ->prefix('superadmin/contacts')
    ->name('superadmin.contacts.')
    ->group($routes);

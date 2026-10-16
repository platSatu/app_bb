<?php

use App\Http\Controllers\Contact\CustomerController;
use Illuminate\Support\Facades\Route;

// Hak akses dicek lagi di controller lewat Customer::accessibleBy().
$routes = function () {
    Route::get('/', [CustomerController::class, 'index'])->name('index');
    Route::get('{customer}', [CustomerController::class, 'show'])->name('show');
};

Route::middleware(['auth', 'permission:contacts.list'])->prefix('contacts')->name('contacts.')->group($routes);

Route::middleware(['auth', 'superadmin', 'permission:superadmin.customers,view'])
    ->prefix('superadmin/contacts')
    ->name('superadmin.contacts.')
    ->group($routes);

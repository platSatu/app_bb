<?php

use App\Http\Controllers\Settings\Branch\BranchController;
use App\Http\Controllers\Settings\Company\CompanyController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')
    ->prefix('settings')
    ->name('settings.')
    ->group(function () {
        Route::get('company', [CompanyController::class, 'show'])->name('company.show');
        Route::get('company/edit', [CompanyController::class, 'edit'])->name('company.edit');
        Route::put('company', [CompanyController::class, 'update'])->middleware('throttle:20,1')->name('company.update');

        Route::resource('branches', BranchController::class)->except('show');
    });

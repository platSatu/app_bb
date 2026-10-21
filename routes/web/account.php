<?php

use App\Http\Controllers\Account\BalanceController;
use App\Http\Controllers\Account\HistoryController;
use App\Http\Controllers\Account\PackageController;
use App\Http\Controllers\Account\ReferralController;
use App\Http\Controllers\Account\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('profile/referral', ReferralController::class)->name('profile.referral');

    Route::get('profile/balance', [BalanceController::class, 'index'])->name('profile.balance');
    Route::get('profile/balance/top-up', [BalanceController::class, 'create'])->name('account.deposits.create');
    Route::post('profile/balance/top-up', [BalanceController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('account.deposits.store');
    Route::get('profile/balance/deposits/{deposit}', [BalanceController::class, 'show'])->name('account.deposits.show');

    Route::get('profile/history', HistoryController::class)->name('profile.history');

    Route::get('profile/packages', [PackageController::class, 'index'])->name('profile.packages');
    Route::post('profile/packages/redeem', [PackageController::class, 'redeem'])->middleware('throttle:10,1')->name('account.packages.redeem');
    Route::post('profile/packages/{package}', [PackageController::class, 'purchase'])->whereUuid('package')->middleware('throttle:10,1')->name('account.packages.purchase');

    Route::controller(WithdrawalController::class)->prefix('profile/withdrawals')->group(function () {
        Route::get('/', 'index')->name('profile.withdrawals');
        Route::post('/', 'store')->middleware('throttle:5,1')->name('account.withdrawals.store');
        Route::post('bank-account', 'storeAccount')->middleware('throttle:5,1')->name('account.withdrawals.account');
        Route::post('{withdrawal}/cancel', 'cancel')->middleware('throttle:10,1')->name('account.withdrawals.cancel');
    });
});

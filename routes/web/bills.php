<?php

use App\Http\Controllers\Account\BillController;
use App\Http\Controllers\Account\BillRegisterController;
use Illuminate\Support\Facades\Route;

// Dashboard Tagihan untuk pembayar: tagihan yang ditujukan ke email akun yang login (lintas perusahaan/cabang).
Route::middleware('auth')->prefix('tagihan')->name('bills.')->group(function () {
    Route::get('/', [BillController::class, 'index'])->name('index');
    Route::post('bayar', [BillController::class, 'pay'])->middleware('throttle:10,1')->name('pay');
    Route::get('kembali/{checkout}', [BillController::class, 'returned'])->whereUuid('checkout')->middleware('throttle:20,1')->name('return');
});

// Link pendaftaran customer Invoice dari owner: daftar akun / login, otomatis jadi customer branch itu, lalu ke Tagihan Saya.
Route::get('tagihan/daftar/{token}', [BillRegisterController::class, 'show'])->middleware('throttle:60,1')->name('bills.register');
Route::post('tagihan/daftar/{token}', [BillRegisterController::class, 'store'])->middleware('throttle:10,1')->name('bills.register.store');

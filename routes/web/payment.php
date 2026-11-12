<?php

use App\Http\Controllers\Payment\DuitkuCallbackController;
use Illuminate\Support\Facades\Route;

// Dipanggil server Duitku (tanpa login, dikecualikan dari CSRF di bootstrap/app.php).
// Batasnya longgar: saat war tiket ribuan callback datang dari beberapa IP Duitku saja. Kalau kena batas, callback ditolak
// SEBELUM tercatat & pesanan tertahan "menunggu". Pengaman utamanya tanda tangan (dicek paling awal) & pemrosesan idempoten.
Route::post('payment/duitku/callback', DuitkuCallbackController::class)
    ->middleware('throttle:3000,1')
    ->name('payment.duitku.callback');

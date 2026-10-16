<?php

use App\Http\Controllers\Payment\DuitkuCallbackController;
use Illuminate\Support\Facades\Route;

// Dipanggil server Duitku (tanpa login, dikecualikan dari CSRF di bootstrap/app.php).
Route::post('payment/duitku/callback', DuitkuCallbackController::class)
    ->middleware('throttle:120,1')
    ->name('payment.duitku.callback');

<?php

use App\Http\Controllers\Invoice\Api\InvoiceApiController;
use App\Http\Controllers\WhatsApp\Api\WaApiController;
use App\Http\Controllers\WhatsApp\WaWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// API Invoice bizbos. Dokumentasi: docs/invoice-api.md atau menu Invoice > API & Integrasi.
Route::prefix('v1')->middleware('invoice.api')->group(function () {
    Route::post('invoices', [InvoiceApiController::class, 'store']);
    Route::get('invoices/{invoice}', [InvoiceApiController::class, 'show']);
    Route::post('invoices/{invoice}/cancel', [InvoiceApiController::class, 'cancel']);
    Route::get('balance', [InvoiceApiController::class, 'balance']);
});

// Webhook dari service WhatsApp (go_bizbos), ditandatangani WA_WEBHOOK_SECRET.
Route::post('webhooks/wa', WaWebhookController::class);

// API WhatsApp per device (API Key di menu WhatsApp > Device & Inbox).
Route::prefix('v1/wa')->middleware('wa.api')->group(function () {
    Route::post('messages', [WaApiController::class, 'send']);
    Route::post('messages/media', [WaApiController::class, 'sendMedia']);
    Route::get('messages/{id}', [WaApiController::class, 'status'])->whereUuid('id');
});

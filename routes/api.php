<?php

use App\Http\Controllers\Api\Frontend\ArticleController as FrontendArticleController;
use App\Http\Controllers\Api\Frontend\CategoryApplicationController as FrontendCategoryApplicationController;
use App\Http\Controllers\Api\Frontend\CategoryVideoController as FrontendCategoryVideoController;
use App\Http\Controllers\Api\Frontend\ChatController as FrontendChatController;
use App\Http\Controllers\Api\Frontend\ContactMessageController as FrontendContactMessageController;
use App\Http\Controllers\Api\Frontend\FaqController as FrontendFaqController;
use App\Http\Controllers\Api\Frontend\FeatureController as FrontendFeatureController;
use App\Http\Controllers\Api\Frontend\FooterController as FrontendFooterController;
use App\Http\Controllers\Api\Frontend\HeaderController as FrontendHeaderController;
use App\Http\Controllers\Api\Frontend\HomeSectionController as FrontendHomeSectionController;
use App\Http\Controllers\Api\Frontend\PackageController as FrontendPackageController;
use App\Http\Controllers\Api\Frontend\PageController as FrontendPageController;
use App\Http\Controllers\Api\Frontend\TermConditionController as FrontendTermConditionController;
use App\Http\Controllers\Api\Frontend\VideoController as FrontendVideoController;
use App\Http\Controllers\Api\Frontend\VisitorLogController as FrontendVisitorLogController;
use App\Http\Controllers\Api\Frontend\WebSettingController as FrontendWebSettingController;
use App\Http\Controllers\Invoice\Api\DisbursementApiController;
use App\Http\Controllers\Invoice\Api\InvoiceApiController;
use App\Http\Controllers\WhatsApp\Api\FormWebhookController;
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

    // Disbursement: kirim dana dari saldo ke rekening penerima (wajib diaktifkan pemilik akun).
    Route::get('banks', [DisbursementApiController::class, 'banks']);
    Route::post('payout-accounts', [DisbursementApiController::class, 'storeAccount'])->middleware('throttle:30,1');
    Route::get('payout-accounts/{account}', [DisbursementApiController::class, 'showAccount']);
    Route::post('payouts', [DisbursementApiController::class, 'store'])->middleware('throttle:30,1');
    Route::get('payouts/{payout}', [DisbursementApiController::class, 'show']);
});

// Webhook dari service WhatsApp (go_bizbos), ditandatangani WA_WEBHOOK_SECRET.
Route::post('webhooks/wa', WaWebhookController::class);

// API WhatsApp per device, format sama dengan teleios (X-WA-Token + X-WA-Secret). Kunci di menu WhatsApp > Third Party > Integrasi Web.
Route::prefix('wa-api/v1')->middleware('wa.api')->group(function () {
    Route::post('send-message', [WaApiController::class, 'send']);
    Route::post('send-media', [WaApiController::class, 'sendMedia']);
    Route::get('messages/{id}', [WaApiController::class, 'status'])->whereUuid('id');
    Route::get('usage', [WaApiController::class, 'usage']);
});

// Google Form (Apps Script) -> balasan WhatsApp. Token di URL adalah kuncinya (menu WhatsApp > Third Party).
Route::post('v1/wa/google-form/{token}', FormWebhookController::class)->where('token', 'gf_[A-Za-z0-9]{48}')->middleware('throttle:120,1')->name('wa.google-form.receive');

// Konten website untuk fe-konexa (bizbos.id), server-to-server dengan header X-API-KEY.
// Alamat & bentuk JSON sama persis dengan API teleios.
Route::prefix('frontend')->middleware('frontend.api-key')->group(function () {
    Route::get('category-applications', [FrontendCategoryApplicationController::class, 'index']);
    // Live chat bizbos.id: jawaban dari konten website (FAQ, fitur, paket) lewat AI aktif.
    Route::post('chat', FrontendChatController::class)->middleware('throttle:120,1');
    Route::post('chat/start', [FrontendChatController::class, 'start'])->middleware('throttle:30,1');
    Route::get('packages', [FrontendPackageController::class, 'index']);
    Route::get('articles', [FrontendArticleController::class, 'index']);
    Route::get('articles/{slug}', [FrontendArticleController::class, 'show'])->where('slug', '[a-z0-9-]+');
    Route::get('faqs', [FrontendFaqController::class, 'index']);
    Route::get('term-condition', [FrontendTermConditionController::class, 'show']);
    Route::get('category-videos', [FrontendCategoryVideoController::class, 'index']);
    Route::get('videos', [FrontendVideoController::class, 'index']);
    Route::get('web-setting', [FrontendWebSettingController::class, 'show']);
    Route::get('features', [FrontendFeatureController::class, 'index']);
    Route::get('headers', [FrontendHeaderController::class, 'index']);
    Route::get('footers', [FrontendFooterController::class, 'index']);
    Route::get('home-sections', [FrontendHomeSectionController::class, 'index']);
    Route::get('pages', [FrontendPageController::class, 'index']);
    Route::get('pages/{slug}', [FrontendPageController::class, 'show'])->where('slug', '[a-z0-9-]+');

    // Satu-satunya endpoint tulis: form Kontak & catatan kunjungan dari server fe-konexa.
    Route::post('contact-messages', [FrontendContactMessageController::class, 'store'])->middleware('throttle:30,1');
    Route::post('visitor-log', [FrontendVisitorLogController::class, 'store']);
});

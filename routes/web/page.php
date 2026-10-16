<?php

use App\Http\Controllers\Page\Biolink\BiolinkController;
use App\Http\Controllers\Page\Catalog\CatalogCategoryController;
use App\Http\Controllers\Page\Catalog\CatalogOrderController;
use App\Http\Controllers\Page\Catalog\CatalogProductController;
use App\Http\Controllers\Page\Category\PageCategoryController;
use App\Http\Controllers\Page\Form\PageFormFieldController;
use App\Http\Controllers\Page\Form\PageSubmissionController;
use App\Http\Controllers\Page\Landing\LandingSectionController;
use App\Http\Controllers\Page\Landing\LandingSettingController;
use App\Http\Controllers\Page\PublicPageController;
use App\Http\Controllers\Page\PublicQuizController;
use App\Http\Controllers\Page\PublicTicketController;
use App\Http\Controllers\Page\Ticket\TicketCategoryController;
use App\Http\Controllers\Page\Ticket\TicketDesignController;
use App\Http\Controllers\Page\Ticket\TicketOrderController;
use App\Http\Controllers\Page\Ticket\TicketScannerController;
use App\Http\Controllers\Page\TicketScanController;
use App\Http\Controllers\Page\Quiz\QuizAttemptController;
use App\Http\Controllers\Page\Quiz\QuizQuestionController;
use App\Http\Controllers\Page\Quiz\QuizSettingController;
use Illuminate\Support\Facades\Route;

// Sub-halaman page dipakai bersama user (/pages) & superadmin (/superadmin/pages).
// Hak akses tetap dicek lagi di controller lewat PageCategory::accessibleBy().
$read = function () {
    Route::get('{page}/builder', [PageFormFieldController::class, 'index'])->name('builder');
    Route::get('{page}/submissions', [PageSubmissionController::class, 'index'])->name('submissions.index');
    Route::get('{page}/submissions/{submission}', [PageSubmissionController::class, 'show'])->name('submissions.show');
    Route::get('{page}/submissions/{submission}/files/{answer}', [PageSubmissionController::class, 'file'])->name('submissions.file');

    Route::get('{page}/catalog/categories', [CatalogCategoryController::class, 'index'])->name('catalog.categories.index');
    Route::get('{page}/catalog/products', [CatalogProductController::class, 'index'])->name('catalog.products.index');
    Route::get('{page}/catalog/orders', [CatalogOrderController::class, 'index'])->name('catalog.orders.index');
    Route::get('{page}/catalog/orders/{order}', [CatalogOrderController::class, 'show'])->name('catalog.orders.show');

    Route::get('{page}/quiz/questions', [QuizQuestionController::class, 'index'])->name('quiz.questions.index');
    Route::get('{page}/quiz/settings', [QuizSettingController::class, 'edit'])->name('quiz.settings.edit');
    Route::get('{page}/quiz/attempts', [QuizAttemptController::class, 'index'])->name('quiz.attempts.index');
    Route::get('{page}/quiz/attempts/{attempt}', [QuizAttemptController::class, 'show'])->name('quiz.attempts.show');

    Route::get('{page}/tickets/categories', [TicketCategoryController::class, 'index'])->name('ticket.categories.index');
    Route::get('{page}/tickets/categories/{category}/sales', [TicketCategoryController::class, 'sales'])->name('ticket.categories.sales');
    Route::get('{page}/tickets/orders', [TicketOrderController::class, 'index'])->name('ticket.orders.index');
    Route::get('{page}/tickets/orders/{order}', [TicketOrderController::class, 'show'])->name('ticket.orders.show');
    Route::get('{page}/tickets/design', [TicketDesignController::class, 'edit'])->name('ticket.design.edit');
    Route::get('{page}/tickets/scanners', [TicketScannerController::class, 'index'])->name('ticket.scanners.index');

    Route::get('{page}/landing/sections', [LandingSectionController::class, 'index'])->name('landing.sections.index');
    Route::post('{page}/landing/preview', [LandingSectionController::class, 'preview'])->middleware('throttle:60,1')->name('landing.preview');
    Route::get('{page}/landing/settings', [LandingSettingController::class, 'edit'])->name('landing.settings.edit');

    Route::get('{page}/biolink', [BiolinkController::class, 'edit'])->name('biolink.edit');
};

$write = function () {
    Route::put('{page}/builder', [PageFormFieldController::class, 'sync'])->middleware('throttle:30,1')->name('builder.sync');

    Route::put('{page}/catalog/categories', [CatalogCategoryController::class, 'sync'])->middleware('throttle:30,1')->name('catalog.categories.sync');
    Route::get('{page}/catalog/products/create', [CatalogProductController::class, 'create'])->name('catalog.products.create');
    Route::post('{page}/catalog/products', [CatalogProductController::class, 'store'])->name('catalog.products.store');
    Route::get('{page}/catalog/products/{product}/edit', [CatalogProductController::class, 'edit'])->name('catalog.products.edit');
    Route::put('{page}/catalog/products/{product}', [CatalogProductController::class, 'update'])->name('catalog.products.update');
    Route::delete('{page}/catalog/products/{product}', [CatalogProductController::class, 'destroy'])->name('catalog.products.destroy');
    Route::patch('{page}/catalog/orders/{order}/status', [CatalogOrderController::class, 'status'])->name('catalog.orders.status');

    Route::put('{page}/quiz/questions', [QuizQuestionController::class, 'sync'])->middleware('throttle:30,1')->name('quiz.questions.sync');
    Route::put('{page}/quiz/settings', [QuizSettingController::class, 'update'])->name('quiz.settings.update');

    Route::put('{page}/tickets/categories', [TicketCategoryController::class, 'sync'])->middleware('throttle:30,1')->name('ticket.categories.sync');
    Route::post('{page}/tickets/orders/{order}/resend', [TicketOrderController::class, 'resend'])->middleware('throttle:10,1')->name('ticket.orders.resend');
    Route::put('{page}/tickets/design', [TicketDesignController::class, 'update'])->name('ticket.design.update');
    Route::post('{page}/tickets/scanners', [TicketScannerController::class, 'store'])->name('ticket.scanners.store');
    Route::patch('{page}/tickets/scanners/{scanner}', [TicketScannerController::class, 'update'])->name('ticket.scanners.update');
    Route::delete('{page}/tickets/scanners/{scanner}', [TicketScannerController::class, 'destroy'])->name('ticket.scanners.destroy');

    Route::put('{page}/landing/sections', [LandingSectionController::class, 'sync'])->middleware('throttle:30,1')->name('landing.sections.sync');
    Route::post('{page}/landing/generate', [LandingSectionController::class, 'generate'])->middleware('throttle:10,1')->name('landing.generate');
    Route::put('{page}/landing/settings', [LandingSettingController::class, 'update'])->name('landing.settings.update');

    Route::put('{page}/biolink', [BiolinkController::class, 'update'])->middleware('throttle:30,1')->name('biolink.update');
};

Route::middleware(['auth', 'permission:pages.list'])->group(function () use ($read, $write) {
    Route::get('pages/{page}/qrcode', [PageCategoryController::class, 'qrcode'])->name('pages.qrcode');
    Route::resource('pages', PageCategoryController::class)->except('show');
    Route::prefix('pages')->name('pages.')->group(function () use ($read, $write) {
        $read();
        $write();
    });
});

Route::middleware(['auth', 'superadmin'])->prefix('superadmin/pages')->name('superadmin.pages.')->group(function () use ($read, $write) {
    Route::middleware('permission:superadmin.pages,view')->group($read);
    Route::middleware('permission:superadmin.pages,manage')->group($write);
});

Route::get('page/{slug}', [PublicPageController::class, 'show'])
    ->where('slug', '[a-z0-9-]+')
    ->middleware('throttle:120,1')
    ->name('page.show');
Route::get('page/{slug}/p/{product}', [PublicPageController::class, 'product'])
    ->where(['slug' => '[a-z0-9-]+', 'product' => '[a-z0-9-]+'])
    ->middleware('throttle:120,1')
    ->name('page.product');
Route::get('page/{slug}/checkout', [PublicPageController::class, 'checkout'])
    ->where('slug', '[a-z0-9-]+')
    ->name('page.checkout');
Route::post('page/{slug}/checkout', [PublicPageController::class, 'placeOrder'])
    ->where('slug', '[a-z0-9-]+')
    ->middleware('throttle:10,1')
    ->name('page.checkout.store');
Route::post('page/{slug}/quiz/start', [PublicQuizController::class, 'start'])
    ->where('slug', '[a-z0-9-]+')
    ->middleware('throttle:10,1')
    ->name('page.quiz.start');
Route::get('page/{slug}/quiz', [PublicQuizController::class, 'play'])
    ->where('slug', '[a-z0-9-]+')
    ->name('page.quiz.play');
Route::post('page/{slug}/quiz', [PublicQuizController::class, 'submit'])
    ->where('slug', '[a-z0-9-]+')
    ->middleware('throttle:10,1')
    ->name('page.quiz.submit');
Route::post('page/{slug}/tickets', [PublicTicketController::class, 'checkout'])
    ->where('slug', '[a-z0-9-]+')
    ->middleware('throttle:10,1')
    ->name('page.tickets.checkout');

// Link e-ticket: token 48 karakter acak, tidak bisa ditebak atau dicari.
Route::middleware('throttle:60,1')->prefix('e-ticket/{slug}')->where(['slug' => '[a-z0-9-]+'])->name('eticket.')->group(function () {
    Route::get('o/{token}', [PublicTicketController::class, 'order'])->where('token', '[A-Za-z0-9]{48}')->name('order');
    Route::get('{number}/{token}', [PublicTicketController::class, 'show'])->where(['number' => 'BB[0-9]{8}', 'token' => '[A-Za-z0-9]{48}'])->name('show');
    Route::get('{number}/{token}/pdf', [PublicTicketController::class, 'pdf'])->where(['number' => 'BB[0-9]{8}', 'token' => '[A-Za-z0-9]{48}'])->name('pdf');
});

// Halaman petugas registrasi ulang: token 48 karakter + PIN.
Route::prefix('scan/{token}')->where(['token' => '[A-Za-z0-9]{48}'])->name('scan.')->group(function () {
    Route::get('/', [TicketScanController::class, 'show'])->middleware('throttle:60,1')->name('show');
    Route::post('login', [TicketScanController::class, 'login'])->middleware('throttle:20,1')->name('login');
    Route::post('check', [TicketScanController::class, 'check'])->middleware('throttle:240,1')->name('check');
    Route::post('logout', [TicketScanController::class, 'logout'])->name('logout');
});

Route::post('page/{slug}', [PublicPageController::class, 'submit'])
    ->where('slug', '[a-z0-9-]+')
    ->middleware('throttle:10,1')
    ->name('page.submit');

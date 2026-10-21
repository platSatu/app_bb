<?php

use App\Http\Controllers\Superadmin\Web\ArticleController;
use App\Http\Controllers\Superadmin\Web\CategoryArticleController;
use App\Http\Controllers\Superadmin\Web\CategoryVideoController;
use App\Http\Controllers\Superadmin\Web\ContactMessageController;
use App\Http\Controllers\Superadmin\Web\FaqController;
use App\Http\Controllers\Superadmin\Web\FeatureController;
use App\Http\Controllers\Superadmin\Web\FooterController;
use App\Http\Controllers\Superadmin\Web\HeaderController;
use App\Http\Controllers\Superadmin\Web\HomeSectionController;
use App\Http\Controllers\Superadmin\Web\HomeSectionItemController;
use App\Http\Controllers\Superadmin\Web\MetaTagController;
use App\Http\Controllers\Superadmin\Web\PageController;
use App\Http\Controllers\Superadmin\Web\SettingController;
use App\Http\Controllers\Superadmin\Web\TermConditionController;
use App\Http\Controllers\Superadmin\Web\VideoController;
use Illuminate\Support\Facades\Route;

// Konten website bizbos (dibaca fe-konexa lewat API). Superadmin > Settings > Website.
// Izin per menu: lihat = permission ...,view; tambah/ubah/hapus = permission ...,manage.
Route::middleware(['auth', 'superadmin'])->prefix('superadmin/web')->name('superadmin.web.')->group(function () {
    $crud = [
        'meta-tags' => MetaTagController::class,
        'category-articles' => CategoryArticleController::class,
        'articles' => ArticleController::class,
        'faqs' => FaqController::class,
        'term-conditions' => TermConditionController::class,
        'category-videos' => CategoryVideoController::class,
        'videos' => VideoController::class,
        'headers' => HeaderController::class,
        'home-sections' => HomeSectionController::class,
        'pages' => PageController::class,
        'features' => FeatureController::class,
        'footers' => FooterController::class,
    ];
    $sortable = ['faqs', 'features', 'home-sections'];

    foreach ($crud as $uri => $controller) {
        $permission = "permission:superadmin.web.{$uri}";

        Route::controller($controller)->prefix($uri)->name("{$uri}.")->group(function () use ($uri, $permission, $sortable) {
            Route::get('/', 'index')->middleware("{$permission},view")->name('index');

            Route::middleware("{$permission},manage")->group(function () use ($uri, $sortable) {
                Route::get('create', 'create')->name('create');
                Route::post('/', 'store')->name('store');
                Route::get('{id}/edit', 'edit')->name('edit');
                Route::put('{id}', 'update')->name('update');
                Route::delete('{id}', 'destroy')->name('destroy');

                if (in_array($uri, $sortable, true)) {
                    Route::post('{id}/move/{direction}', 'move')->whereIn('direction', ['up', 'down'])->name('move');
                }
            });
        });
    }

    // Item di dalam section Susunan Beranda (ikut izin Susunan Beranda).
    Route::controller(HomeSectionItemController::class)->middleware('permission:superadmin.web.home-sections,manage')->group(function () {
        Route::get('home-sections/{section}/items/create', 'create')->name('home-sections.items.create');
        Route::post('home-sections/{section}/items', 'store')->name('home-sections.items.store');
        Route::get('home-section-items/{id}/edit', 'edit')->name('home-section-items.edit');
        Route::put('home-section-items/{id}', 'update')->name('home-section-items.update');
        Route::delete('home-section-items/{id}', 'destroy')->name('home-section-items.destroy');
        Route::post('home-section-items/{id}/move/{direction}', 'move')->whereIn('direction', ['up', 'down'])->name('home-section-items.move');
    });

    Route::controller(ContactMessageController::class)->prefix('contact-messages')->name('contact-messages.')->group(function () {
        Route::get('/', 'index')->middleware('permission:superadmin.web.contact-messages,view')->name('index');
        Route::get('{id}', 'show')->middleware('permission:superadmin.web.contact-messages,view')->name('show');
        Route::patch('{id}/status', 'updateStatus')->middleware('permission:superadmin.web.contact-messages,manage')->name('status');
        Route::delete('{id}', 'destroy')->middleware('permission:superadmin.web.contact-messages,manage')->name('destroy');
    });

    Route::controller(SettingController::class)->prefix('setting')->name('setting.')->group(function () {
        Route::get('/', 'edit')->middleware('permission:superadmin.web.setting,view')->name('edit');
        Route::put('/', 'update')->middleware('permission:superadmin.web.setting,manage')->name('update');
    });
});

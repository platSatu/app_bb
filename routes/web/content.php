<?php

use App\Http\Controllers\Superadmin\Tools\ContentController;
use App\Http\Controllers\Superadmin\Tools\ContentMediaController;
use App\Http\Controllers\Superadmin\Tools\SocialAccountController;
use Illuminate\Support\Facades\Route;

/*
| Tools › Konten Harian (Agent Konten) & Tools › Akun Sosmed. Keduanya khusus superadmin (biaya AI & token akun resmi).
| Callback OAuth (isi persis di console developer masing-masing):
|   Meta:   /superadmin/tools/social/callback/meta    (Facebook Login › Valid OAuth Redirect URIs)
|   TikTok: /superadmin/tools/social/callback/tiktok  (Login Kit › Redirect URI)
|   Google: /superadmin/tools/social/callback/google  (OAuth client › Authorized redirect URIs)
*/

// File media untuk diambil Instagram/Facebook/TikTok saat posting: hanya lewat link bertanda tangan (berlaku beberapa jam).
Route::get('konten/media/{post}/{file}', ContentMediaController::class)
    ->whereUuid('post')->where('file', '\d{1,2}\.[a-z0-9]{3,4}')
    ->middleware(['signed', 'throttle:120,1'])
    ->name('content.media');

Route::middleware(['auth', 'superadmin'])->prefix('superadmin/tools')->name('superadmin.tools.')->group(function () {
    Route::controller(ContentController::class)->prefix('content')->name('content.')->group(function () {
        $view = 'permission:superadmin.tools.content,view';
        $manage = 'permission:superadmin.tools.content,manage';

        Route::get('/', 'index')->middleware($view)->name('index');
        Route::get('settings', 'settings')->middleware($view)->name('settings');
        Route::put('settings', 'updateSettings')->middleware([$manage, 'throttle:20,1'])->name('settings.update');
        Route::post('plan', 'plan')->middleware([$manage, 'throttle:5,10'])->name('plan');
        Route::get('plan/status', 'planStatus')->middleware([$view, 'throttle:60,1'])->name('plan.status');
        Route::post('plan/stop', 'stopPlan')->middleware($manage)->name('plan.stop');
        Route::post('bulk', 'bulk')->middleware([$manage, 'throttle:30,1'])->name('bulk');
        Route::post('publications/{publication}/retry', 'retry')->whereUuid('publication')->middleware([$manage, 'throttle:20,1'])->name('retry');

        Route::prefix('{post}')->group(function () use ($view, $manage) {
            Route::put('/', 'update')->whereUuid('post')->middleware([$manage, 'throttle:60,1'])->name('update');
            Route::post('regenerate', 'regenerate')->whereUuid('post')->middleware([$manage, 'throttle:10,1'])->name('regenerate');
            Route::post('media', 'media')->whereUuid('post')->middleware([$manage, 'throttle:20,1'])->name('media');
            Route::post('upload', 'upload')->whereUuid('post')->middleware([$manage, 'throttle:10,1'])->name('upload');
            Route::post('schedule', 'schedule')->whereUuid('post')->middleware([$manage, 'throttle:30,1'])->name('schedule');
            Route::post('unschedule', 'unschedule')->whereUuid('post')->middleware([$manage, 'throttle:30,1'])->name('unschedule');
            Route::get('file/{index}', 'file')->whereUuid('post')->whereNumber('index')->middleware($view)->name('file');
        });
    });

    Route::controller(SocialAccountController::class)->prefix('social')->name('social.')
        ->middleware('permission:superadmin.tools.social,manage')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::put('apps/{provider}', 'saveApp')->whereIn('provider', ['meta', 'tiktok', 'google'])->middleware('throttle:10,1')->name('apps.save');
            Route::post('connect/{provider}', 'connect')->whereIn('provider', ['meta', 'tiktok', 'google'])->middleware('throttle:10,1')->name('connect');
            Route::get('callback/{provider}', 'callback')->whereIn('provider', ['meta', 'tiktok', 'google'])->middleware('throttle:20,1')->name('callback');
            Route::post('accounts/{account}/refresh', 'refresh')->whereUuid('account')->middleware('throttle:10,1')->name('refresh');
            Route::put('accounts/{account}', 'update')->whereUuid('account')->name('update');
            Route::delete('accounts/{account}', 'destroy')->whereUuid('account')->name('destroy');
        });
});

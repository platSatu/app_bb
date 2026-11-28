<?php

use App\Http\Controllers\Superadmin\Tools\InboxController;
use Illuminate\Support\Facades\Route;

/*
| Tools › Inbox (khusus superadmin): WhatsApp branch superadmin + DM & komentar sosmed resmi bizbos dalam satu daftar.
| Webhook Meta ada di routes/api.php: /api/webhooks/meta (Callback URL di dashboard Meta › Webhooks).
*/
Route::middleware(['auth', 'superadmin'])->prefix('superadmin/tools/inbox')->name('superadmin.tools.inbox.')
    ->controller(InboxController::class)->group(function () {
        $view = 'permission:superadmin.tools.inbox,view';
        $manage = 'permission:superadmin.tools.inbox,manage';

        Route::get('/', 'index')->middleware($view)->name('index');
        Route::put('settings', 'settings')->middleware([$manage, 'throttle:20,1'])->name('settings');
        Route::post('accounts/{account}/subscribe', 'subscribe')->whereUuid('account')->middleware([$manage, 'throttle:10,1'])->name('subscribe');
        Route::post('accounts/{account}/sync', 'syncYoutube')->whereUuid('account')->middleware([$manage, 'throttle:10,1'])->name('sync');
        Route::get('{thread}', 'show')->whereUuid('thread')->middleware($view)->name('show');
        Route::post('{thread}/reply', 'reply')->whereUuid('thread')->middleware([$manage, 'throttle:30,1'])->name('reply');
        Route::post('{thread}/draft', 'draft')->whereUuid('thread')->middleware([$manage, 'throttle:20,1'])->name('draft');
        Route::put('{thread}', 'update')->whereUuid('thread')->middleware([$manage, 'throttle:60,1'])->name('update');
    });

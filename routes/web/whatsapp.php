<?php

use App\Http\Controllers\WhatsApp\AiBotController;
use App\Http\Controllers\WhatsApp\ApiKeyController;
use App\Http\Controllers\WhatsApp\AutoReplyController;
use App\Http\Controllers\WhatsApp\BroadcastController;
use App\Http\Controllers\WhatsApp\ChatbotFlowController;
use App\Http\Controllers\WhatsApp\ChatDetailController;
use App\Http\Controllers\WhatsApp\ContactController;
use App\Http\Controllers\WhatsApp\ContactGroupController;
use App\Http\Controllers\WhatsApp\DeviceController;
use App\Http\Controllers\WhatsApp\FormCheckinController;
use App\Http\Controllers\WhatsApp\GoogleFormController;
use App\Http\Controllers\WhatsApp\InboxController;
use App\Http\Controllers\WhatsApp\LabelController;
use App\Http\Controllers\WhatsApp\QuickReplyController;
use App\Http\Controllers\WhatsApp\TemplateController;
use App\Http\Controllers\WhatsApp\ThirdPartyController;
use App\Http\Controllers\WhatsApp\WaGroupController;
use Illuminate\Support\Facades\Route;

// Owner. Semua data dibatasi ke branch miliknya lewat InWhatsAppArea.
Route::middleware('auth')->prefix('whatsapp')->name('whatsapp.')->group(function () {
    Route::middleware('permission:whatsapp.devices')->group(function () {
        Route::controller(DeviceController::class)->prefix('devices')->name('devices.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('list', 'list')->name('list');
            Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
            Route::put('{device}', 'update')->whereUuid('device')->name('update');
            Route::delete('{device}', 'destroy')->whereUuid('device')->name('destroy');
            Route::post('{device}/connect', 'connect')->whereUuid('device')->middleware('throttle:10,1')->name('connect');
            Route::get('{device}/status', 'status')->whereUuid('device')->name('status');
            Route::post('{device}/logout', 'logout')->whereUuid('device')->name('logout');
            Route::get('{device}/health', 'health')->whereUuid('device')->name('health');
        });

        Route::controller(ApiKeyController::class)->prefix('devices/{device}/api-key')->whereUuid('device')->name('api-key.')->group(function () {
            Route::get('/', 'show')->name('show');
            Route::post('/', 'store')->name('store');
            Route::post('regenerate', 'regenerate')->middleware('throttle:10,1')->name('regenerate');
            Route::post('toggle', 'toggle')->name('toggle');
        });

        Route::prefix('devices/{device}/inbox')->whereUuid('device')->name('inbox.')->group(function () {
            Route::controller(InboxController::class)->group(function () {
                Route::get('/', 'show')->name('show');
                Route::get('chats', 'chats')->name('chats');
                Route::get('chats/{chat}', 'chat')->whereUuid('chat')->name('chat');
                Route::get('chats/{chat}/messages', 'messages')->whereUuid('chat')->name('messages');
                Route::post('chats/{chat}/read', 'read')->whereUuid('chat')->name('read');
                Route::post('chats/{chat}/presence', 'presence')->whereUuid('chat')->middleware('throttle:30,1')->name('presence');
                Route::get('chats/{chat}/avatar', 'avatar')->whereUuid('chat')->name('avatar');
                Route::post('send', 'send')->middleware('throttle:60,1')->name('send');
                Route::post('send-media', 'sendMedia')->middleware('throttle:20,1')->name('send-media');
                Route::post('send-poll', 'sendPoll')->middleware('throttle:10,1')->name('send-poll');
                Route::get('messages/{message}/media', 'media')->whereUuid('message')->name('media');
                Route::get('messages/{message}/poll', 'pollResults')->whereUuid('message')->name('poll');
                Route::get('realtime', 'realtime')->middleware('throttle:30,1')->name('realtime');
                Route::get('quick-replies', 'quickReplies')->name('quick-replies');
            });

            Route::controller(ChatDetailController::class)->prefix('chats/{chat}')->whereUuid('chat')->group(function () {
                Route::get('detail', 'show')->name('detail');
                Route::get('media-list', 'media')->name('media-list');
                Route::post('labels/{label}', 'attachLabel')->whereUuid('label')->name('labels.attach');
                Route::delete('labels/{label}', 'detachLabel')->whereUuid('label')->name('labels.detach');
                Route::post('notes', 'storeNote')->name('notes.store');
                Route::put('notes/{note}', 'updateNote')->whereUuid('note')->name('notes.update');
                Route::delete('notes/{note}', 'destroyNote')->whereUuid('note')->name('notes.destroy');
                Route::post('assign', 'assign')->name('assign');
                Route::post('bot', 'bot')->name('bot');
                Route::post('contact', 'saveContact')->name('contact');
                Route::get('vcard', 'vcard')->name('vcard');
            });
        });
    });

    Route::middleware('permission:whatsapp.contacts')->controller(ContactController::class)->prefix('contacts')->name('contacts.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('throttle:60,1')->name('store');
        Route::post('import', 'import')->middleware('throttle:5,1')->name('import');
        Route::get('contoh-excel', 'template')->name('template');
        Route::put('{customer}', 'update')->whereUuid('customer')->name('update');
        Route::delete('{customer}', 'destroy')->whereUuid('customer')->name('destroy');
        Route::post('{customer}/akun', 'account')->whereUuid('customer')->middleware('throttle:20,1')->name('account');
    });

    // Third Party > Integrasi Web: API Key per nomor + dokumentasi (tab).
    Route::get('third-party', [ThirdPartyController::class, 'index'])->middleware('permission:whatsapp.third-party')->name('third-party.index');

    // Third Party > Google Form: daftar, pengaturan, laporan & export.
    Route::middleware('permission:whatsapp.google-forms')->controller(GoogleFormController::class)->prefix('google-form')->name('google-forms.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
        Route::get('{form}', 'show')->whereUuid('form')->name('show');
        Route::get('{form}/edit', 'edit')->whereUuid('form')->name('edit');
        Route::put('{form}', 'update')->whereUuid('form')->name('update');
        Route::delete('{form}', 'destroy')->whereUuid('form')->name('destroy');
        Route::post('{form}/regenerate', 'regenerate')->whereUuid('form')->middleware('throttle:10,1')->name('regenerate');
        Route::get('{form}/export', 'export')->whereUuid('form')->middleware('throttle:10,1')->name('export');
        Route::post('{form}/pendaftar/{submission}/kirim-qr', 'resend')->whereUuid(['form', 'submission'])->middleware('throttle:20,1')->name('resend');
    });

    Route::middleware('permission:whatsapp.groups')->controller(ContactGroupController::class)->prefix('groups')->name('groups.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('{group}', 'show')->whereUuid('group')->name('show');
        Route::put('{group}', 'update')->whereUuid('group')->name('update');
        Route::delete('{group}', 'destroy')->whereUuid('group')->name('destroy');
        Route::post('{group}/members', 'addMembers')->whereUuid('group')->name('members.store');
        Route::delete('{group}/members/{customer}', 'removeMember')->whereUuid(['group', 'customer'])->name('members.destroy');
    });

    Route::middleware('permission:whatsapp.wa-groups')->controller(WaGroupController::class)->prefix('wa-groups')->name('wa-groups.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('import', 'import')->middleware('throttle:10,1')->name('import');
    });

    // Settings
    Route::middleware('permission:whatsapp.messages')->controller(BroadcastController::class)->prefix('messages')->name('messages.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
        Route::get('{broadcast}', 'show')->whereUuid('broadcast')->name('show');
        Route::get('{broadcast}/progress', 'progress')->whereUuid('broadcast')->name('progress');
        Route::post('{broadcast}/pause', 'pause')->whereUuid('broadcast')->name('pause');
        Route::post('{broadcast}/resume', 'resume')->whereUuid('broadcast')->name('resume');
        Route::post('{broadcast}/cancel', 'cancel')->whereUuid('broadcast')->name('cancel');
        Route::delete('{broadcast}', 'destroy')->whereUuid('broadcast')->name('destroy');
    });

    Route::middleware('permission:whatsapp.quick-replies')->controller(QuickReplyController::class)->prefix('quick-replies')->name('quick-replies.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('{reply}', 'update')->whereUuid('reply')->name('update');
        Route::delete('{reply}', 'destroy')->whereUuid('reply')->name('destroy');
    });

    Route::middleware('permission:whatsapp.templates')->controller(TemplateController::class)->prefix('templates')->name('templates.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->middleware('throttle:30,1')->name('store');
        Route::get('{template}/edit', 'edit')->whereUuid('template')->name('edit');
        Route::put('{template}', 'update')->whereUuid('template')->middleware('throttle:30,1')->name('update');
        Route::delete('{template}', 'destroy')->whereUuid('template')->name('destroy');
        Route::get('{template}/file', 'file')->whereUuid('template')->name('file');
    });

    Route::middleware('permission:whatsapp.labels')->controller(LabelController::class)->prefix('labels')->name('labels.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('{label}', 'update')->whereUuid('label')->name('update');
        Route::delete('{label}', 'destroy')->whereUuid('label')->name('destroy');
    });

    Route::middleware('permission:whatsapp.flows')->controller(ChatbotFlowController::class)->prefix('flows')->name('flows.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('{flow}/edit', 'edit')->whereUuid('flow')->name('edit');
        Route::put('{flow}', 'update')->whereUuid('flow')->name('update');
        Route::delete('{flow}', 'destroy')->whereUuid('flow')->name('destroy');
    });

    Route::middleware('permission:whatsapp.auto-replies')->controller(AutoReplyController::class)->prefix('auto-replies')->name('auto-replies.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('{rule}', 'update')->whereUuid('rule')->name('update');
        Route::delete('{rule}', 'destroy')->whereUuid('rule')->name('destroy');
    });

    Route::middleware('permission:whatsapp.ai-bots')->controller(AiBotController::class)->prefix('ai-bots')->name('ai-bots.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('{device}', 'edit')->whereUuid('device')->name('edit');
        Route::put('{device}', 'update')->whereUuid('device')->name('update');
        Route::post('{device}/test', 'test')->whereUuid('device')->middleware('throttle:5,1')->name('test');
        Route::delete('{device}', 'destroy')->whereUuid('device')->name('destroy');
    });
});

// Dokumentasi API WhatsApp versi publik: link aman dibagikan ke developer (tanpa data akun).
Route::get('docs/api-whatsapp', [ThirdPartyController::class, 'publicDocs'])->middleware('throttle:60,1')->name('docs.whatsapp-api');

// Google Form: QR pendaftar dan aplikasi registrasi ulang petugas (tanpa login, kunci = kode/token + PIN).
Route::get('r/{code}', [FormCheckinController::class, 'ticket'])->where('code', '[A-Za-z0-9]{10}')->middleware('throttle:60,1')->name('form-ticket.show');
Route::controller(FormCheckinController::class)->prefix('registrasi-ulang/{token}')->where(['token' => '[A-Za-z0-9]{48}'])->name('form-checkin.')->group(function () {
    Route::get('/', 'show')->middleware('throttle:60,1')->name('show');
    Route::post('login', 'login')->middleware('throttle:20,1')->name('login');
    Route::post('logout', 'logout')->name('logout');
    Route::post('check', 'check')->middleware('throttle:120,1')->name('check');
});

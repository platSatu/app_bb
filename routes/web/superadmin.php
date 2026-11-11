<?php

use App\Http\Controllers\Superadmin\Activity\ActivityController;
use App\Http\Controllers\Superadmin\Ai\AiController;
use App\Http\Controllers\Superadmin\Backup\BackupController;
use App\Http\Controllers\Superadmin\Monitor\MonitorController;
use App\Http\Controllers\Superadmin\Branch\BranchController;
use App\Http\Controllers\Superadmin\CategoryApplication\CategoryApplicationController;
use App\Http\Controllers\Superadmin\Company\CompanyController;
use App\Http\Controllers\Superadmin\Deposit\AdjustmentController;
use App\Http\Controllers\Superadmin\Deposit\DepositController;
use App\Http\Controllers\Superadmin\Deposit\MutationController;
use App\Http\Controllers\Superadmin\Email\EmailLogController;
use App\Http\Controllers\Superadmin\Package\PackageController;
use App\Http\Controllers\Superadmin\Page\PageController;
use App\Http\Controllers\Superadmin\Page\PageTypeController;
use App\Http\Controllers\Superadmin\Page\TicketFeeController;
use App\Http\Controllers\Superadmin\PaymentGateway\PaymentGatewayController;
use App\Http\Controllers\Superadmin\Referral\ReferralController;
use App\Http\Controllers\Superadmin\Role\RoleController;
use App\Http\Controllers\Superadmin\Tools\EmailBlastController;
use App\Http\Controllers\Superadmin\Tools\GooglePlacesController;
use App\Http\Controllers\Superadmin\Tools\ProspectController;
use App\Http\Controllers\Superadmin\User\UserController;
use App\Http\Controllers\Superadmin\Voucher\VoucherController;
use App\Http\Controllers\Superadmin\Withdrawal\BankAccountController;
use App\Http\Controllers\Superadmin\Withdrawal\DisbursementController;
use App\Http\Controllers\Superadmin\Withdrawal\PayoutController;
use App\Http\Controllers\Superadmin\Withdrawal\WithdrawalController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

Route::bind('role', fn (string $id) => Role::platform()->findOrFail($id));

Route::middleware(['auth', 'superadmin'])
    ->prefix('superadmin')
    ->name('superadmin.')
    ->group(function () {
        $crud = [
            'users' => ['superadmin.users', UserController::class],
            'companies' => ['superadmin.companies', CompanyController::class],
            'branches' => ['superadmin.branches', BranchController::class],
            'category-applications' => ['superadmin.category-applications', CategoryApplicationController::class],
            'packages' => ['superadmin.packages', PackageController::class],
            'pages' => ['superadmin.pages', PageController::class],
            'page-types' => ['superadmin.page-types', PageTypeController::class],
        ];

        Route::get('ticket-fee', [TicketFeeController::class, 'edit'])->middleware('permission:superadmin.events,view')->name('ticket-fee.edit');
        Route::put('ticket-fee', [TicketFeeController::class, 'update'])->middleware('permission:superadmin.events,manage')->name('ticket-fee.update');

        Route::get('pages/{page}/qrcode', [PageController::class, 'qrcode'])
            ->middleware('permission:superadmin.pages,view')
            ->name('pages.qrcode');

        // Applications › Event: page bertemplate tiket, controller sama dengan Pages (area dibaca dari nama route).
        Route::get('events/{page}/qrcode', [PageController::class, 'qrcode'])
            ->middleware('permission:superadmin.events,view')
            ->name('events.qrcode');
        Route::get('events', [PageController::class, 'index'])
            ->middleware('permission:superadmin.events,view')
            ->name('events.index');
        Route::resource('events', PageController::class)
            ->parameters(['events' => 'page'])
            ->except(['index', 'show'])
            ->middleware('permission:superadmin.events,manage');

        foreach ($crud as $uri => [$permission, $controller]) {
            Route::get($uri, [$controller, 'index'])
                ->middleware("permission:{$permission},view")
                ->name("{$uri}.index");
            Route::resource($uri, $controller)
                ->except(['index', 'show'])
                ->middleware("permission:{$permission},manage");
        }

        Route::get('vouchers', [VoucherController::class, 'index'])
            ->middleware('permission:superadmin.vouchers,view')
            ->name('vouchers.index');

        Route::get('referral', [ReferralController::class, 'index'])
            ->middleware('permission:superadmin.referral,view')
            ->name('referral.index');
        Route::put('referral', [ReferralController::class, 'update'])
            ->middleware('permission:superadmin.referral,manage')
            ->name('referral.update');
        Route::post('referral/rates', [ReferralController::class, 'rate'])
            ->middleware(['permission:superadmin.referral,manage', 'throttle:30,1'])
            ->name('referral.rate');
        Route::post('referral/commissions/{commission}/cancel', [ReferralController::class, 'cancel'])
            ->whereUuid('commission')
            ->middleware(['permission:superadmin.referral,manage', 'throttle:30,1'])
            ->name('referral.cancel');

        Route::controller(DepositController::class)
            ->prefix('deposits')
            ->name('deposits.')
            ->middleware('permission:superadmin.deposits,view')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('history', 'history')->name('history');
                Route::get('balances', 'balances')->name('balances');
            });

        Route::controller(AdjustmentController::class)
            ->prefix('wallet-adjustment')
            ->name('wallet-adjustment.')
            ->middleware('permission:superadmin.wallet-adjustment,manage')
            ->group(function () {
                Route::get('/', 'create')->name('create');
                Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
            });

        // Mutasi Duitku: semua uang masuk (Kredit) & keluar (Debit) dari semua sumber. Hanya baca.
        Route::get('mutations', [MutationController::class, 'index'])
            ->middleware(['permission:superadmin.mutations,view', 'throttle:60,1'])
            ->name('mutations.index');
        Route::get('mutations/duitku-balance', [MutationController::class, 'duitkuBalance'])
            ->middleware(['permission:superadmin.mutations,view', 'throttle:30,1'])
            ->name('mutations.duitku-balance');
        Route::get('mutations/profit', [MutationController::class, 'profit'])
            ->middleware(['permission:superadmin.mutations,view', 'throttle:60,1'])
            ->name('mutations.profit');
        Route::get('mutations/logs', [MutationController::class, 'logs'])
            ->middleware(['permission:superadmin.mutations,view', 'throttle:60,1'])
            ->name('mutations.logs');

        // Tarik saldo: khusus superadmin (uang keluar dari Duitku).
        Route::middleware('permission:superadmin.withdrawals,manage')->group(function () {
            Route::controller(WithdrawalController::class)->prefix('withdrawals')->name('withdrawals.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('{withdrawal}/approve', 'approve')->middleware('throttle:20,1')->name('approve');
                Route::post('{withdrawal}/reject', 'reject')->name('reject');
                Route::post('{withdrawal}/check', 'check')->middleware('throttle:20,1')->name('check');
                Route::post('{withdrawal}/resolve', 'resolve')->name('resolve');
            });

            // Disbursement owner (menu Invoice › Disbursement): pantau & selesaikan transfer yang belum pasti.
            Route::controller(PayoutController::class)->prefix('payouts')->name('payouts.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('{disbursement}/check', 'check')->middleware('throttle:20,1')->name('check');
                Route::post('{disbursement}/resolve', 'resolve')->name('resolve');
            });

            Route::get('bank-accounts', [BankAccountController::class, 'index'])->name('bank-accounts.index');
            Route::post('bank-accounts/{account}/review', [BankAccountController::class, 'review'])->name('bank-accounts.review');

            Route::controller(DisbursementController::class)->prefix('disbursement')->name('disbursement.')->group(function () {
                Route::get('/', 'setting')->name('setting');
                Route::put('/', 'update')->name('update');
                Route::post('balance', 'balance')->middleware('throttle:10,1')->name('balance');
            });
        });

        Route::controller(PaymentGatewayController::class)
            ->prefix('payment-gateway')
            ->name('payment-gateway.')
            ->group(function () {
                Route::get('/', 'setting')->middleware('permission:superadmin.payment-gateway,view')->name('setting');
                Route::put('/', 'updateSetting')->middleware('permission:superadmin.payment-gateway,manage')->name('setting.update');
                Route::post('fee-rules', 'storeFeeRule')->middleware(['permission:superadmin.payment-gateway,manage', 'throttle:30,1'])->name('fee-rules.store');
                Route::delete('fee-rules/{rule}', 'destroyFeeRule')->whereUuid('rule')->middleware('permission:superadmin.payment-gateway,manage')->name('fee-rules.destroy');
            });

        Route::controller(AiController::class)
            ->prefix('ai')
            ->name('ai.')
            ->middleware('permission:superadmin.ai,manage')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->name('store');
                Route::put('quota', 'quota')->name('quota');
                Route::put('{provider}', 'update')->name('update');
                Route::post('{provider}/activate', 'activate')->name('activate');
                Route::post('{provider}/test', 'test')->middleware('throttle:10,1')->name('test');
                Route::delete('{provider}', 'destroy')->name('destroy');
            });

        Route::controller(MonitorController::class)
            ->prefix('monitor')
            ->name('monitor.')
            ->middleware('permission:superadmin.monitor,manage')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('retry', 'retry')->middleware('throttle:20,1')->name('retry');
                Route::delete('failed', 'forget')->name('forget');
                Route::delete('jobs/{job}', 'destroyJob')->whereNumber('job')->name('jobs.destroy');
                Route::post('process', 'process')->middleware('throttle:5,1')->name('process');
            });

        Route::controller(EmailLogController::class)
            ->prefix('emails')
            ->name('emails.')
            ->middleware('permission:superadmin.emails,manage')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('{emailLog}/resend', 'resend')->middleware('throttle:20,1')->name('resend');
            });

        Route::controller(ActivityController::class)
            ->prefix('activity')
            ->name('activity.')
            ->middleware('permission:superadmin.activity,view')
            ->group(function () {
                Route::get('/', 'visitors')->name('visitors');
                Route::get('users', 'users')->name('users');
            });

        Route::controller(BackupController::class)
            ->prefix('backups')
            ->name('backups.')
            ->middleware('permission:superadmin.backups,manage')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('/', 'store')->middleware('throttle:5,1')->name('store');
                Route::get('{file}', 'download')->where('file', '[A-Za-z0-9_.\-]+')->name('download');
            });

        // Tools › Google Places. Dibatasi 20x/menit; batas harian ada di GooglePlacesClient.
        Route::get('tools/google-places', [GooglePlacesController::class, 'index'])
            ->middleware(['permission:superadmin.tools.google-places,view', 'throttle:20,1'])
            ->name('tools.google-places.index');
        // Tools › Open Places: halaman & controller yang sama, sumber data Open Places (gratis).
        Route::get('tools/open-places', [GooglePlacesController::class, 'index'])
            ->defaults('provider', 'open')
            ->middleware(['permission:superadmin.tools.open-places,view', 'throttle:20,1'])
            ->name('tools.open-places.index');
        Route::post('tools/open-places/export', [GooglePlacesController::class, 'export'])
            ->middleware(['permission:superadmin.tools.open-places,view', 'throttle:20,1'])
            ->name('tools.open-places.export');
        Route::post('tools/open-places/save', [GooglePlacesController::class, 'save'])
            ->middleware(['permission:superadmin.tools.prospects,manage', 'throttle:20,1'])
            ->name('tools.open-places.save');

        // Tools › Prospek: hasil Open Places yang disimpan, untuk follow-up.
        Route::controller(ProspectController::class)->prefix('tools/prospects')->name('tools.prospects.')->group(function () {
            Route::get('/', 'index')->middleware('permission:superadmin.tools.prospects,view')->name('index');
            Route::get('export', 'export')->middleware(['permission:superadmin.tools.prospects,view', 'throttle:10,1'])->name('export');
            Route::get('agent', 'agent')->middleware('permission:superadmin.tools.prospects,view')->name('agent');
            Route::put('agent', 'updateAgent')->middleware(['permission:superadmin.tools.prospects,manage', 'throttle:20,1'])->name('agent.update');
            Route::post('agent/run', 'runAgent')->middleware(['permission:superadmin.tools.prospects,manage', 'throttle:3,10'])->name('agent.run');
            Route::put('agent/templates', 'updateTemplates')->middleware(['permission:superadmin.tools.prospects,manage', 'throttle:20,1'])->name('agent.templates');
            Route::post('agent/templates/ai', 'aiTemplates')->middleware(['permission:superadmin.tools.prospects,manage', 'throttle:3,10'])->name('agent.templates.ai');
            Route::put('agent/send', 'updateSending')->middleware(['permission:superadmin.tools.prospects,manage', 'throttle:20,1'])->name('agent.send');
            Route::post('agent/templates/test', 'testTemplate')->middleware(['permission:superadmin.tools.prospects,manage', 'throttle:10,10'])->name('agent.templates.test');
            Route::get('import/template', 'importTemplate')->middleware('permission:superadmin.tools.prospects,view')->name('import.template');
            Route::post('bulk-delete', 'bulkDestroy')->middleware(['permission:superadmin.tools.prospects,manage', 'throttle:20,1'])->name('bulk-destroy');
            Route::post('import', 'import')->middleware(['permission:superadmin.tools.prospects,manage', 'throttle:10,10'])->name('import');
            Route::get('outreach', 'outreach')->middleware('permission:superadmin.tools.prospects,view')->name('outreach');
            Route::post('outreach/bulk', 'bulkOutreach')->middleware(['permission:superadmin.tools.prospects,manage', 'throttle:30,1'])->name('outreach.bulk');
            Route::put('outreach/{prospect}', 'updateOutreach')->middleware(['permission:superadmin.tools.prospects,manage', 'throttle:60,1'])->name('outreach.update');
            Route::put('{prospect}', 'update')->middleware('permission:superadmin.tools.prospects,manage')->name('update');
            Route::delete('{prospect}', 'destroy')->middleware('permission:superadmin.tools.prospects,manage')->name('destroy');
        });

        // Tools › Email Blast: kirim email penawaran & baca inbox dari akun email hosting tersendiri.
        Route::controller(EmailBlastController::class)->prefix('tools/email-blast')->name('tools.email-blast.')
            ->middleware('permission:superadmin.tools.email-blast,manage')->group(function () {
                Route::get('/', 'index')->middleware('throttle:30,1')->name('index');
                Route::get('inbox/{uid}', 'message')->whereNumber('uid')->middleware('throttle:30,1')->name('message');
                Route::get('sent/{blast}', 'show')->name('show');
                Route::get('prospects', 'prospects')->middleware('throttle:60,1')->name('prospects');
                Route::post('/', 'store')->middleware('throttle:10,1')->name('store');
                Route::post('sent/{blast}/resume', 'resume')->middleware('throttle:10,1')->name('resume');
                Route::put('settings', 'updateSettings')->middleware('throttle:10,1')->name('settings');
            });

        Route::resource('roles', RoleController::class)
            ->except('show')
            ->middleware('permission:superadmin.roles,manage');
    });

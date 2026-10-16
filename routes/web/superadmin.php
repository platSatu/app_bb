<?php

use App\Http\Controllers\Superadmin\Ai\AiController;
use App\Http\Controllers\Superadmin\Branch\BranchController;
use App\Http\Controllers\Superadmin\CategoryApplication\CategoryApplicationController;
use App\Http\Controllers\Superadmin\Company\CompanyController;
use App\Http\Controllers\Superadmin\Deposit\AdjustmentController;
use App\Http\Controllers\Superadmin\Deposit\DepositController;
use App\Http\Controllers\Superadmin\Package\PackageController;
use App\Http\Controllers\Superadmin\Page\PageController;
use App\Http\Controllers\Superadmin\Page\PageTypeController;
use App\Http\Controllers\Superadmin\PaymentGateway\PaymentGatewayController;
use App\Http\Controllers\Superadmin\Referral\ReferralController;
use App\Http\Controllers\Superadmin\Role\RoleController;
use App\Http\Controllers\Superadmin\User\UserController;
use App\Http\Controllers\Superadmin\Voucher\VoucherController;
use App\Http\Controllers\Superadmin\Withdrawal\BankAccountController;
use App\Http\Controllers\Superadmin\Withdrawal\DisbursementController;
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

        Route::get('pages/{page}/qrcode', [PageController::class, 'qrcode'])
            ->middleware('permission:superadmin.pages,view')
            ->name('pages.qrcode');

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

        Route::controller(DepositController::class)
            ->prefix('deposits')
            ->name('deposits.')
            ->middleware('permission:superadmin.deposits,view')
            ->group(function () {
                Route::get('/', 'index')->name('index');
                Route::get('history', 'history')->name('history');
            });

        Route::controller(AdjustmentController::class)
            ->prefix('wallet-adjustment')
            ->name('wallet-adjustment.')
            ->middleware('permission:superadmin.wallet-adjustment,manage')
            ->group(function () {
                Route::get('/', 'create')->name('create');
                Route::post('/', 'store')->middleware('throttle:20,1')->name('store');
            });

        // Tarik saldo: khusus superadmin (uang keluar dari Duitku).
        Route::middleware('permission:superadmin.withdrawals,manage')->group(function () {
            Route::controller(WithdrawalController::class)->prefix('withdrawals')->name('withdrawals.')->group(function () {
                Route::get('/', 'index')->name('index');
                Route::post('{withdrawal}/approve', 'approve')->middleware('throttle:20,1')->name('approve');
                Route::post('{withdrawal}/reject', 'reject')->name('reject');
                Route::post('{withdrawal}/check', 'check')->middleware('throttle:20,1')->name('check');
                Route::post('{withdrawal}/resolve', 'resolve')->name('resolve');
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

        Route::resource('roles', RoleController::class)
            ->except('show')
            ->middleware('permission:superadmin.roles,manage');
    });

<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\BankAccountRequest;
use App\Http\Requests\Account\WithdrawalRequest;
use App\Models\BankAccount;
use App\Models\DisbursementSetting;
use App\Models\PaymentGatewaySetting;
use App\Models\WalletWithdrawal;
use App\Services\Payment\DuitkuDisbursementClient;
use App\Services\Withdrawal\BankAccountService;
use App\Services\Wallet\WalletService;
use App\Services\Withdrawal\WithdrawalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class WithdrawalController extends Controller
{
    public function index(Request $request, WalletService $wallets): View
    {
        $user = $request->user();
        $summary = $wallets->summary($user);
        $setting = DisbursementSetting::current();
        $accounts = BankAccount::where('user_id', $user->getKey())->whereIn('status', [BankAccount::STATUS_APPROVED, BankAccount::STATUS_PENDING, BankAccount::STATUS_REJECTED])->latest()->get();
        $available = $setting->is_active && $setting->isConfigured();
        $banks = [];
        $banksError = null;

        if ($available) {
            try {
                $banks = DuitkuDisbursementClient::make()->banks();
            } catch (Throwable $e) {
                report($e);
                $banksError = 'Daftar bank sedang tidak bisa dimuat. Coba muat ulang halaman beberapa saat lagi.';
            }
        }

        return view('profile.withdrawal.index', [
            'setting' => $setting,
            'available' => $available,
            'balance' => $summary['balance'],
            'withdrawable' => $summary['available'],
            'pendingItems' => $summary['pending'] > 0 ? $wallets->pendingItems($user) : collect(),
            'gateway' => PaymentGatewaySetting::duitku(),
            'approved' => $accounts->firstWhere('status', BankAccount::STATUS_APPROVED),
            'pending' => $accounts->firstWhere('status', BankAccount::STATUS_PENDING),
            'rejected' => $accounts->where('status', BankAccount::STATUS_REJECTED)->first(),
            'banks' => $banks,
            'banksError' => $banksError,
            'withdrawals' => WalletWithdrawal::where('user_id', $user->getKey())->latest()->paginate(10),
        ]);
    }

    public function storeAccount(BankAccountRequest $request, BankAccountService $service): RedirectResponse
    {
        try {
            $account = $service->submit($request->user(), $request->validated('bank_code'), $request->validated('account_number'));
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', "Rekening {$account->masked()} a.n. {$account->account_name} sudah kami terima dan sedang diverifikasi admin. Biasanya tidak lama, ya.");
    }

    public function store(WithdrawalRequest $request, WithdrawalService $service): RedirectResponse
    {
        try {
            $withdrawal = $service->request($request->user(), (int) $request->validated('amount'), $request->filled('fee') ? $request->integer('fee') : null);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', "Pengajuan {$withdrawal->reference} berhasil dikirim. Saldo Rp ".number_format($withdrawal->amount, 0, ',', '.').' kami tahan dulu sampai admin memproses. Kalau ditolak atau gagal, saldo otomatis kembali.');
    }

    public function cancel(Request $request, string $withdrawal, WithdrawalService $service): RedirectResponse
    {
        $withdrawal = WalletWithdrawal::where('user_id', $request->user()->getKey())->findOrFail($withdrawal);

        try {
            $service->cancel($withdrawal, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Pengajuan {$withdrawal->reference} dibatalkan dan saldo sudah kembali ke akun Anda.");
    }
}

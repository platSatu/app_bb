<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\TopUpRequest;
use App\Models\PaymentGatewaySetting;
use App\Services\Deposit\DepositService;
use App\Services\Wallet\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** Semua data di sini selalu milik user yang login (lewat relasi $request->user()). */
class BalanceController extends Controller
{
    public function index(Request $request, WalletService $wallets): View
    {
        $user = $request->user();
        $summary = $wallets->summary($user);

        return view('profile.balance.index', [
            'balance' => $summary['balance'],
            'withdrawable' => $summary['available'],
            'transactions' => $user->walletTransactions()->latest()->limit(10)->get(),
            'deposits' => $user->deposits()->latest()->limit(5)->get(),
        ]);
    }

    public function create(): View
    {
        return view('profile.balance.top-up', ['setting' => PaymentGatewaySetting::duitku()]);
    }

    public function store(TopUpRequest $request, DepositService $deposits): RedirectResponse
    {
        try {
            $deposit = $deposits->create($request->user(), (int) $request->validated('amount'));
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->away($deposit->payment_url);
    }

    public function show(Request $request, string $deposit): View
    {
        return view('profile.balance.deposit', [
            'deposit' => $request->user()->deposits()->findOrFail($deposit),
        ]);
    }
}

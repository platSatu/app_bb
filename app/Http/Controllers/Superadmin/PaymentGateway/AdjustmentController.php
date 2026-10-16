<?php

namespace App\Http\Controllers\Superadmin\PaymentGateway;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\PaymentGateway\AdjustmentRequest;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\Wallet\InsufficientBalanceException;
use App\Services\Wallet\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdjustmentController extends Controller
{
    public function __construct(private readonly WalletService $wallets)
    {
    }

    public function create(Request $request): View
    {
        $email = trim((string) $request->query('email'));
        $target = $email !== '' ? User::where('email', $email)->first() : null;

        return view('superadmin.payment-gateway.adjustment', [
            'email' => $email,
            'target' => $target,
            'balance' => $target ? $this->wallets->balanceOf($target) : null,
            // Token sekali pakai: form yang terkirim 2x tetap hanya 1 mutasi saldo.
            'token' => (string) Str::uuid(),
        ]);
    }

    public function store(AdjustmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = User::where('email', $data['email'])->firstOrFail();
        $method = $data['direction'] === WalletTransaction::CREDIT ? 'credit' : 'debit';

        try {
            $transaction = $this->wallets->{$method}(
                $user,
                (int) $data['amount'],
                WalletTransaction::TYPE_ADJUSTMENT,
                'adjustment:'.$data['token'],
                null,
                'Koreksi admin: '.$data['reason'],
                $request->user(),
            );
        } catch (InsufficientBalanceException $e) {
            return back()->withInput()->withErrors([
                'amount' => 'Saldo user hanya Rp '.number_format($e->balance, 0, ',', '.').', tidak cukup untuk dikurangi sebesar itu.',
            ]);
        }

        return redirect()->route('superadmin.payment-gateway.history', ['search' => $user->email])
            ->with('success', "Saldo {$user->name} berhasil dikoreksi. Saldo sekarang Rp ".number_format($transaction->balance_after, 0, ',', '.').'.');
    }
}

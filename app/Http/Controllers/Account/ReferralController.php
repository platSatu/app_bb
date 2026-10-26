<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\ReferralCommission;
use App\Models\ReferralSetting;
use App\Services\Referral\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ReferralController extends Controller
{
    public function __construct(private readonly ReferralService $referrals)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $commissions = ReferralCommission::where('referrer_id', $user->getKey());

        return view('profile.referral', [
            'referralCode' => $this->referrals->codeFor($user),
            'setting' => ReferralSetting::current(),
            'totals' => (clone $commissions)->selectRaw('status, SUM(amount) AS total')->groupBy('status')->pluck('total', 'status'),
            'commissions' => $commissions->with('buyer:id,name')->latest()->paginate(10, ['*'], 'komisi'),
            'referrals' => $user->referrals()->with('referredUser:id,name')->latest()->paginate(10, ['*'], 'ajakan'),
        ]);
    }

    public function share(Request $request): RedirectResponse
    {
        $percent = (int) $request->validate(['share_percent' => ['required', 'integer', 'min:0', 'max:100']], [], ['share_percent' => 'bagian untuk teman'])['share_percent'];

        try {
            $this->referrals->setShare($request->user(), $percent);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['share_percent' => $e->getMessage()]);
        }

        return back()->with('success', $percent ? "Teman yang Anda ajak sekarang dapat potongan {$percent}% setiap beli paket." : 'Seluruh komisi sekarang untuk Anda; teman yang diajak tidak dapat potongan.');
    }
}

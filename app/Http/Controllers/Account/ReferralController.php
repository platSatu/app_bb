<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\ReferralSetting;
use App\Services\Referral\ReferralService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferralController extends Controller
{
    public function __invoke(Request $request, ReferralService $referrals): View
    {
        $user = $request->user();

        return view('profile.referral', [
            'referralCode' => $referrals->codeFor($user),
            'setting' => ReferralSetting::current(),
            'referrals' => $user->referrals()
                ->with('referredUser:id,name,created_at')
                ->latest()
                ->paginate(10),
        ]);
    }
}

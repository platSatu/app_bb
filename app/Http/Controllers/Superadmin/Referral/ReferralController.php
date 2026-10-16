<?php

namespace App\Http\Controllers\Superadmin\Referral;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\Referral\ReferralSettingRequest;
use App\Models\Referral;
use App\Models\ReferralSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReferralController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $referrals = Referral::query()
            ->with(['referrer:id,name,email', 'referredUser:id,name,email'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$search}%")
                ->orWhereHas('referrer', fn ($u) => $u->search($search))
                ->orWhereHas('referredUser', fn ($u) => $u->search($search))))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.referral.index', ['setting' => ReferralSetting::current(), 'referrals' => $referrals]);
    }

    public function update(ReferralSettingRequest $request): RedirectResponse
    {
        $setting = ReferralSetting::current();
        $setting->fill($request->safe()->except('is_active'));
        $setting->is_active = $request->boolean('is_active');
        $setting->save();

        return back()->with('success', 'Pengaturan bonus referral berhasil disimpan.');
    }
}

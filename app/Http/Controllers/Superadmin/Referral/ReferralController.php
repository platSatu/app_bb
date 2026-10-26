<?php

namespace App\Http\Controllers\Superadmin\Referral;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\Referral\ReferralSettingRequest;
use App\Models\ReferralCode;
use App\Models\ReferralCommission;
use App\Models\ReferralSetting;
use App\Models\User;
use App\Services\Referral\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** Superadmin > Data User > Referral: aturan komisi, persen khusus per user, dan komisi (batalkan selama ditahan). */
class ReferralController extends Controller
{
    public function __construct(private readonly ReferralService $referrals)
    {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $commissions = ReferralCommission::query()
            ->with(['referrer:id,name,email', 'buyer:id,name,email'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('referrer', fn ($u) => $u->search($search))
                ->orWhereHas('buyer', fn ($u) => $u->search($search))))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.referral.index', [
            'setting' => ReferralSetting::current(),
            'commissions' => $commissions,
            'totals' => ReferralCommission::selectRaw('status, COUNT(*) AS count, SUM(amount) AS total')->groupBy('status')->get()->keyBy('status'),
            'customRates' => ReferralCode::with('user:id,name,email')->whereNotNull('commission_percent')->orderByDesc('commission_percent')->get(),
        ]);
    }

    public function update(ReferralSettingRequest $request): RedirectResponse
    {
        $setting = ReferralSetting::current();
        $setting->fill($request->safe()->except('is_active'));
        $setting->is_active = $request->boolean('is_active');
        $setting->save();

        return back()->with('success', 'Pengaturan komisi referral berhasil disimpan.');
    }

    /** Persen khusus satu user (kosong = kembali ke persen default). */
    public function rate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
            'commission_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
        ], ['email.exists' => 'User dengan email ini tidak ditemukan.'], ['commission_percent' => 'persen komisi']);

        $user = User::where('email', $data['email'])->firstOrFail();
        $percent = isset($data['commission_percent']) ? (int) $data['commission_percent'] : null;
        $this->referrals->setRate($user, $percent);

        return back()->with('success', $percent === null
            ? "Komisi {$user->name} kembali ke persen default."
            : "Komisi {$user->name} sekarang {$percent}%. Berlaku untuk pembelian berikutnya; komisi yang sudah tercatat tidak berubah.");
    }

    public function cancel(Request $request, ReferralCommission $commission): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']], [], ['reason' => 'alasan'])['reason'];

        try {
            $this->referrals->cancel($commission, $request->user(), $reason);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Komisi dibatalkan dan tidak akan masuk ke saldo pengajak.');
    }
}

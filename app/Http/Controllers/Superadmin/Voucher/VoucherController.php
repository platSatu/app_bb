<?php

namespace App\Http\Controllers\Superadmin\Voucher;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Voucher;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class VoucherController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $vouchers = Voucher::query()
            ->with(['package:id,name', 'owner:id,name,email', 'redeemer:id,name,email'])
            ->status($request->query('status'))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$search}%")
                ->orWhereHas('owner', fn ($u) => $u->search($search))
                ->orWhereHas('redeemer', fn ($u) => $u->search($search))))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        // Paket untuk voucher gratis: semua paket non-trial yang punya layanan, termasuk yang tidak dijual di katalog.
        $packages = Package::where('is_trial', false)->orderBy('name')->get()->filter(fn (Package $p) => ! empty($p->apps));

        return view('superadmin.voucher.index', compact('vouchers', 'packages'));
    }

    /** Buat kode voucher gratis (mis. untuk tempat kursus yang butuh waktu coba lebih lama). */
    public function store(Request $request, SubscriptionService $subscriptions): RedirectResponse
    {
        $data = $request->validate([
            'package_id' => ['required', Rule::exists('packages', 'id')->where('is_trial', false)],
            'count' => ['required', 'integer', 'between:1,50'],
            'valid_days' => ['required', 'integer', 'between:1,365'],
        ], [], ['package_id' => 'paket', 'count' => 'jumlah kode', 'valid_days' => 'masa berlaku kode']);

        try {
            $vouchers = $subscriptions->issue(Package::findOrFail($data['package_id']), $request->user(), $data['count'], $data['valid_days']);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        Log::info('[VOUCHER] Kode gratis dibuat superadmin', ['user' => $request->user()->id, 'package' => $data['package_id'], 'count' => $vouchers->count()]);

        return redirect()->route('superadmin.vouchers.index')
            ->with('success', $vouchers->count().' kode voucher dibuat. Berikan kodenya ke tempat kursus; dipakai lewat Paket Saya › Redeem Voucher.')
            ->with('codes', $vouchers->pluck('code')->all());
    }
}

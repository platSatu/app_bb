<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\BranchSubscription;
use App\Models\Package;
use App\Models\SubscriptionHistory;
use App\Models\UserBranch;
use App\Models\Voucher;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Paket Saya: layanan aktif per branch, beli paket dari saldo, dan pakai kode voucher. Hanya untuk owner. */
class PackageController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if($user->isPlatformTeam(), 403);

        $branches = $this->branches($request);
        $usedTrial = $this->subscriptions->hasUsedTrial($user);

        return view('profile.packages.index', [
            'branches' => $branches,
            'subscriptions' => BranchSubscription::whereIn('branch_id', $branches->modelKeys())->get()->groupBy('branch_id'),
            'packages' => Package::active()->with('category:id,name')->whereNotNull('apps')
                ->when($usedTrial, fn ($q) => $q->where('is_trial', false))
                ->orderByDesc('is_trial')->orderBy('price')->get()
                ->filter(fn ($p) => ! empty($p->apps))
                ->groupBy(fn ($p) => $p->category?->name ?? 'Paket'),
            'vouchers' => Voucher::with('package:id,name,duration,apps')->where('user_id', $user->getKey())
                ->whereNull('redeemed_at')->where('valid_until', '>=', now())->latest()->get(),
            'histories' => SubscriptionHistory::with(['branch:id,name', 'package:id,name'])->whereIn('branch_id', $branches->modelKeys())
                ->latest()->limit(15)->get(),
            'balance' => (int) $user->wallet?->balance,
        ]);
    }

    public function purchase(Request $request, Package $package): RedirectResponse
    {
        abort_if($request->user()->isPlatformTeam(), 403);
        $data = $request->validate([
            'mode' => ['required', Rule::in(['activate', 'voucher'])],
            'branch_id' => ['required_if:mode,activate', 'nullable', Rule::in($this->branches($request)->modelKeys())],
        ], ['branch_id.required_if' => 'Pilih branch yang mau diaktifkan.'], ['branch_id' => 'branch']);

        $branch = $data['mode'] === 'activate' ? UserBranch::findOrFail($data['branch_id']) : null;

        try {
            $voucher = $this->subscriptions->purchase($request->user(), $package, $branch);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $branch
            ? "Paket {$package->name} aktif di {$branch->name}. Layanannya sudah bisa dipakai sekarang."
            : "Paket {$package->name} tersimpan sebagai kode voucher {$voucher->code}. Berlaku ".SubscriptionService::VOUCHER_VALID_DAYS.' hari sebelum dipakai.');
    }

    public function redeem(Request $request): RedirectResponse
    {
        abort_if($request->user()->isPlatformTeam(), 403);
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'branch_id' => ['required', Rule::in($this->branches($request)->modelKeys())],
        ], [], ['code' => 'kode voucher', 'branch_id' => 'branch']);

        $voucher = Voucher::where('code', $data['code'])->first();

        if (! $voucher) {
            return back()->withInput()->with('error', 'Kode voucher tidak ditemukan. Periksa lagi penulisannya ya.');
        }

        $branch = UserBranch::findOrFail($data['branch_id']);

        try {
            $this->subscriptions->redeem($voucher, $branch, $request->user());
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', "Voucher berhasil dipakai. Paket {$voucher->package?->name} aktif di {$branch->name}.");
    }

    private function branches(Request $request)
    {
        return UserBranch::where('user_id', $request->user()->getKey())->orderBy('name')->get(['id', 'name', 'status']);
    }
}

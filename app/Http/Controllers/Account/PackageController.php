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
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Owner: Paket Saya (status & riwayat), katalog + checkout paket (bayar pakai saldo), dan redeem voucher. */
class PackageController extends Controller implements HasMiddleware
{
    public function __construct(private readonly SubscriptionService $subscriptions)
    {
    }

    /** Hanya pemilik usaha; tim platform tidak membeli paket. */
    public static function middleware(): array
    {
        return [new Middleware(fn ($request, $next) => $request->user()->isPlatformTeam() ? abort(403) : $next($request))];
    }

    public function index(Request $request): View
    {
        $branches = $this->branches($request);

        return view('profile.packages.index', [
            'branches' => $branches,
            'subscriptions' => BranchSubscription::whereIn('branch_id', $branches->modelKeys())->get()->groupBy('branch_id'),
            'histories' => SubscriptionHistory::with(['branch:id,name', 'package:id,name'])->whereIn('branch_id', $branches->modelKeys())->latest()->limit(15)->get(),
        ]);
    }

    public function catalog(Request $request): View
    {
        $packages = $this->forSale($request)->get();
        $category = $request->query('kategori');

        return view('profile.packages.catalog', [
            'categories' => $packages->pluck('category')->filter()->unique('id')->sortBy('name')->values(),
            'packages' => $category ? $packages->where('category_application_id', $category)->values() : $packages,
            'activeCategory' => $category,
            'balance' => (int) $request->user()->wallet?->balance,
        ]);
    }

    public function show(Request $request, string $package): View|RedirectResponse
    {
        $model = $this->forSale($request)->find($package);

        if (! $model) {
            return redirect()->route('packages.catalog')->with('info', 'Paket ini sudah tidak tersedia, atau trial-nya sudah pernah Anda pakai.');
        }

        $branches = $this->branches($request);

        return view('profile.packages.show', [
            'package' => $model,
            'branches' => $branches,
            // Tanggal berakhir sekarang per branch per layanan, untuk pratinjau masa aktif setelah beli.
            'ends' => BranchSubscription::whereIn('branch_id', $branches->modelKeys())->whereIn('app_key', $model->apps)->get()
                ->groupBy('branch_id')->map(fn ($rows) => $rows->mapWithKeys(fn ($s) => [$s->app_key => $s->ends_at->toIso8601String()])),
            'balance' => (int) $request->user()->wallet?->balance,
        ]);
    }

    public function purchase(Request $request, Package $package): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['activate', 'voucher'])],
            'branch_id' => ['required_if:mode,activate', 'nullable', Rule::in($this->branches($request)->modelKeys())],
        ], ['branch_id.required_if' => 'Pilih branch yang mau diaktifkan.'], ['branch_id' => 'branch']);

        $branch = $data['mode'] === 'activate' ? UserBranch::findOrFail($data['branch_id']) : null;

        try {
            $voucher = $this->subscriptions->purchase($request->user(), $package, $branch);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route($branch ? 'profile.packages' : 'packages.redeem')->with('success', $branch
            ? "Paket {$package->name} aktif di {$branch->name}. Menunya sudah bisa dipakai sekarang."
            : "Paket {$package->name} tersimpan sebagai kode voucher {$voucher->code}. Berlaku ".SubscriptionService::VOUCHER_VALID_DAYS.' hari sebelum dipakai.');
    }

    public function redeemForm(Request $request): View
    {
        return view('profile.packages.redeem', [
            'branches' => $this->branches($request),
            'vouchers' => Voucher::with('package:id,name,duration,apps')->where('user_id', $request->user()->getKey())
                ->whereNull('redeemed_at')->where('valid_until', '>=', now())->latest()->get(),
        ]);
    }

    public function redeem(Request $request): RedirectResponse
    {
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

        return redirect()->route('profile.packages')->with('success', "Voucher berhasil dipakai. Paket {$voucher->package?->name} aktif di {$branch->name}.");
    }

    /** Paket yang sedang dijual; trial disembunyikan kalau sudah pernah dipakai. */
    private function forSale(Request $request)
    {
        return Package::active()->with('category:id,name')->whereJsonLength('apps', '>', 0)
            ->when($this->subscriptions->hasUsedTrial($request->user()), fn ($q) => $q->where('is_trial', false))
            ->orderByDesc('is_trial')->orderBy('price');
    }

    private function branches(Request $request): Collection
    {
        return UserBranch::where('user_id', $request->user()->getKey())->orderBy('name')->get(['id', 'name', 'status']);
    }
}

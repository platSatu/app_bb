<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Marketplace\Concerns\InMarketplaceArea;
use App\Models\MarketplaceApp;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Contracts\MarketplaceChannel;
use App\Services\Marketplace\MarketplaceException;
use App\Services\Marketplace\MarketplaceManager;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Sambungkan toko marketplace ke page katalog (OAuth "code for token"), perpanjang token & putuskan.
 * Sama untuk semua channel; turunan cukup menyebut channel-nya (Lazada/Shopee/...).
 *
 * Alamat callback satu per channel untuk semua page & app: page dan app dibawa lewat parameter state
 * yang disimpan di session (sekaligus pencegah CSRF, sesuai anjuran dokumentasi Lazada & Shopee).
 */
abstract class StoreAuthController extends Controller
{
    use InMarketplaceArea;

    private const STATE_TTL_MINUTES = 30;

    public function __construct(protected readonly MarketplaceManager $manager) {}

    /** Kunci channel di config/marketplace.php. */
    abstract protected function channelKey(): string;

    protected function channel(): MarketplaceChannel
    {
        return $this->manager->channel($this->channelKey());
    }

    public function connect(Request $request, string $page): RedirectResponse
    {
        $model = $this->catalogPage($request, $page, Menu::ABILITY_MANAGE);
        $appId = $request->validate(['app_id' => ['nullable', 'uuid']])['app_id'] ?? null;

        if (! $app = MarketplaceApp::pickFor($this->channelKey(), $appId)) {
            return back()->with('error', 'App '.$this->label().' belum diatur. Minta superadmin mengisinya di Superadmin › Marketplace Apps.');
        }

        $state = Str::random(40);
        $request->session()->put("marketplace.oauth.{$state}", [
            'channel' => $this->channelKey(),
            'page_id' => $model->getKey(),
            'app_id' => $app->getKey(),
            'expires' => now()->addMinutes(self::STATE_TTL_MINUTES)->timestamp,
        ]);

        try {
            return redirect()->away($this->channel()->authorizeUrl($app, $state));
        } catch (MarketplaceException $e) {
            $request->session()->forget("marketplace.oauth.{$state}");

            return back()->with('error', $e->getMessage());
        }
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = (string) $request->query('state');
        $pending = $state !== '' ? $request->session()->pull("marketplace.oauth.{$state}") : null;

        if (! $pending || ($pending['channel'] ?? null) !== $this->channelKey() || ($pending['expires'] ?? 0) < now()->timestamp) {
            return redirect()->route('pages.index')->with('error', 'Sesi otorisasi '.$this->label().' tidak dikenali atau sudah kedaluwarsa. Ulangi dari tab Marketplace (buka bizbos dari domain yang sama dengan Callback URL).');
        }

        $page = $this->catalogPage($request, $pending['page_id'], Menu::ABILITY_MANAGE);
        $back = redirect()->route('pages.marketplace.index', $page);

        if (! $code = $request->query('code')) {
            return $back->with('error', 'Otorisasi '.$this->label().' dibatalkan atau ditolak.');
        }

        $app = MarketplaceApp::forChannel($this->channelKey())->find($pending['app_id']);
        if (! $app) {
            return $back->with('error', 'App '.$this->label().' yang dipakai sudah dihapus superadmin. Coba sambungkan lagi.');
        }

        try {
            $data = $this->channel()->exchangeCode($app, (string) $code, $request->query());
        } catch (MarketplaceException $e) {
            return $back->with('error', 'Gagal menyambungkan '.$this->label().': '.$e->getMessage());
        }

        $key = ['page_id' => $page->getKey(), 'channel' => $this->channelKey(), 'external_id' => $data['external_id']];
        $store = MarketplaceStore::where($key)->first() ?? (new MarketplaceStore())->forceFill($key);
        $isNew = ! $store->exists;

        $store->forceFill([
            'branch_id' => $page->branch_id,
            'app_id' => $app->getKey(),
            'name' => $data['name'] ?: $store->name,
            'country' => $data['country'] ?: $store->country,
            'account' => $data['account'] ?: $store->account,
            'short_code' => $data['short_code'] ?: $store->short_code,
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'access_expires_at' => $data['access_expires_at'],
            'refresh_expires_at' => $data['refresh_expires_at'],
            'status' => MarketplaceStore::STATUS_CONNECTED,
            'status_reason' => null,
            'meta' => $data['meta'],
        ])->save();

        return $back->with('success', 'Toko '.$this->label().' '.($isNew ? 'tersambung' : 'diperbarui').': '.$store->displayName().'.');
    }

    public function refresh(Request $request, string $page, string $store): RedirectResponse
    {
        $model = $this->catalogPage($request, $page, Menu::ABILITY_MANAGE);
        $item = $this->findStore($model, $store, $this->channelKey());

        try {
            $this->channel()->refreshToken($item);
        } catch (MarketplaceException $e) {
            return back()->with('error', 'Token belum bisa diperpanjang: '.$e->getMessage().' Silakan sambungkan ulang tokonya.');
        }

        return back()->with('success', 'Token toko '.$item->displayName().' diperpanjang sampai '.$item->access_expires_at?->translatedFormat('d M Y H:i').'.');
    }

    public function disconnect(Request $request, string $page, string $store): RedirectResponse
    {
        $model = $this->catalogPage($request, $page, Menu::ABILITY_MANAGE);
        $item = $this->findStore($model, $store, $this->channelKey());
        $name = $item->displayName();
        $item->delete();

        return back()->with('success', "Toko {$name} diputus dari page ini. Produk di ".$this->label().' tidak ikut terhapus.');
    }

    protected function label(): string
    {
        return config('marketplace.channels.'.$this->channelKey().'.label', ucfirst($this->channelKey()));
    }
}

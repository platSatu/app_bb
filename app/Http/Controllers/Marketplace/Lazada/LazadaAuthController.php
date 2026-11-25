<?php

namespace App\Http\Controllers\Marketplace\Lazada;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Marketplace\Concerns\InMarketplaceArea;
use App\Models\MarketplaceApp;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Lazada\LazadaChannel;
use App\Services\Marketplace\MarketplaceException;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Sambungkan toko Lazada ke page katalog (OAuth "code for token"), perpanjang token & putuskan.
 * Alamat callback satu untuk semua page & app: page dan app dibawa lewat parameter state (disimpan di session).
 */
class LazadaAuthController extends Controller
{
    use InMarketplaceArea;

    private const CHANNEL = 'lazada';

    private const STATE_TTL_MINUTES = 30;

    public function __construct(private readonly LazadaChannel $lazada) {}

    public function connect(Request $request, string $page): RedirectResponse
    {
        $model = $this->catalogPage($request, $page, Menu::ABILITY_MANAGE);
        $appId = $request->validate(['app_id' => ['nullable', 'uuid']])['app_id'] ?? null;

        if (! $app = MarketplaceApp::pickFor(self::CHANNEL, $appId)) {
            return back()->with('error', 'App Lazada belum diatur. Minta superadmin mengisi App Key & App Secret di Superadmin › Marketplace Apps.');
        }

        $state = Str::random(40);
        $request->session()->put("marketplace.oauth.{$state}", [
            'channel' => self::CHANNEL,
            'page_id' => $model->getKey(),
            'app_id' => $app->getKey(),
            'expires' => now()->addMinutes(self::STATE_TTL_MINUTES)->timestamp,
        ]);

        return redirect()->away($this->lazada->authorizeUrl($app, $state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = (string) $request->query('state');
        $pending = $state !== '' ? $request->session()->pull("marketplace.oauth.{$state}") : null;

        if (! $pending || ($pending['channel'] ?? null) !== self::CHANNEL || ($pending['expires'] ?? 0) < now()->timestamp) {
            return redirect()->route('pages.index')->with('error', 'Sesi otorisasi Lazada tidak dikenali atau sudah kedaluwarsa. Ulangi dari tab Marketplace.');
        }

        $page = $this->catalogPage($request, $pending['page_id'], Menu::ABILITY_MANAGE);
        $back = redirect()->route('pages.marketplace.index', $page);

        if (! $code = $request->query('code')) {
            return $back->with('error', 'Otorisasi Lazada dibatalkan atau ditolak.');
        }

        $app = MarketplaceApp::forChannel(self::CHANNEL)->find($pending['app_id']);
        if (! $app) {
            return $back->with('error', 'App Lazada yang dipakai sudah dihapus superadmin. Coba sambungkan lagi.');
        }

        try {
            $data = $this->lazada->exchangeCode($app, (string) $code);
        } catch (MarketplaceException $e) {
            return $back->with('error', 'Gagal menyambungkan Lazada: '.$e->getMessage());
        }

        $key = ['page_id' => $page->getKey(), 'channel' => self::CHANNEL, 'external_id' => $data['external_id']];
        $store = MarketplaceStore::where($key)->first() ?? (new MarketplaceStore())->forceFill($key);
        $isNew = ! $store->exists;

        $store->forceFill([
            'branch_id' => $page->branch_id,
            'app_id' => $app->getKey(),
            'name' => $data['name'] ?: $store->name,
            'country' => $data['country'],
            'account' => $data['account'],
            'short_code' => $data['short_code'],
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'access_expires_at' => $data['access_expires_at'],
            'refresh_expires_at' => $data['refresh_expires_at'],
            'status' => MarketplaceStore::STATUS_CONNECTED,
            'status_reason' => null,
            'meta' => $data['meta'],
        ])->save();

        return $back->with('success', ($isNew ? 'Toko Lazada tersambung: ' : 'Toko Lazada diperbarui: ').$store->displayName().'.');
    }

    public function refresh(Request $request, string $page, string $store): RedirectResponse
    {
        $model = $this->catalogPage($request, $page, Menu::ABILITY_MANAGE);
        $item = $this->findStore($model, $store, self::CHANNEL);

        try {
            $this->lazada->refreshToken($item);
        } catch (MarketplaceException $e) {
            return back()->with('error', 'Token belum bisa diperpanjang: '.$e->getMessage().' Silakan sambungkan ulang tokonya.');
        }

        return back()->with('success', 'Token toko '.$item->displayName().' diperpanjang sampai '.$item->access_expires_at?->translatedFormat('d M Y H:i').'.');
    }

    public function disconnect(Request $request, string $page, string $store): RedirectResponse
    {
        $model = $this->catalogPage($request, $page, Menu::ABILITY_MANAGE);
        $item = $this->findStore($model, $store, self::CHANNEL);
        $name = $item->displayName();
        $item->delete();

        return back()->with('success', "Toko {$name} diputus dari page ini. Produk di Lazada tidak ikut terhapus.");
    }
}

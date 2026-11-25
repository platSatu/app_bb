<?php

namespace App\Http\Controllers\Superadmin\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceApp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Superadmin › Marketplace Apps: kredensial app developer (Lazada App Key/Secret, nanti TikTok & Shopee).
 * Boleh beberapa app per channel (akun developer berbeda); satu ditandai default untuk sambungan baru.
 */
class MarketplaceAppController extends Controller
{
    public function index(Request $request): View
    {
        return view('superadmin.marketplace.apps.index', [
            'apps' => MarketplaceApp::withCount('stores')
                ->when($request->query('channel'), fn ($q, $c) => $q->forChannel($c))
                ->orderBy('channel')->orderByDesc('is_default')->orderBy('name')
                ->get(),
            'channels' => config('marketplace.channels'),
        ]);
    }

    public function create(): View
    {
        return $this->form(new MarketplaceApp(['channel' => 'lazada', 'region' => 'id', 'environment' => MarketplaceApp::ENV_LIVE, 'status' => MarketplaceApp::STATUS_ACTIVE]));
    }

    public function store(Request $request): RedirectResponse
    {
        $app = new MarketplaceApp();
        $this->save($app, $this->validated($request, $app));

        return redirect()->route('superadmin.marketplace-apps.index')->with('success', "App {$app->name} ditambahkan.");
    }

    public function edit(string $app): View
    {
        return $this->form(MarketplaceApp::findOrFail($app));
    }

    public function update(Request $request, string $app): RedirectResponse
    {
        $model = MarketplaceApp::findOrFail($app);
        $this->save($model, $this->validated($request, $model));

        return redirect()->route('superadmin.marketplace-apps.index')->with('success', "App {$model->name} diperbarui.");
    }

    public function destroy(string $app): RedirectResponse
    {
        $model = MarketplaceApp::withCount('stores')->findOrFail($app);

        if ($model->stores_count > 0) {
            return back()->with('error', "App {$model->name} masih dipakai {$model->stores_count} toko. Nonaktifkan saja, atau minta owner memutus tokonya dulu.");
        }

        $model->delete();

        return back()->with('success', "App {$model->name} dihapus.");
    }

    private function form(MarketplaceApp $app): View
    {
        return view('superadmin.marketplace.apps.form', [
            'app' => $app,
            'channels' => config('marketplace.channels'),
            'regions' => MarketplaceApp::REGIONS,
        ]);
    }

    private function validated(Request $request, MarketplaceApp $app): array
    {
        $channel = $request->input('channel', $app->channel);

        return $request->validate([
            'channel' => ['required', Rule::in(array_keys(config('marketplace.channels')))],
            'name' => ['required', 'string', 'max:100'],
            'app_key' => ['required', 'string', 'max:100', ...($channel === 'shopee' ? ['regex:/^\d+$/'] : []), Rule::unique('marketplace_apps')->where('channel', $channel)->ignore($app->getKey())],
            // Saat edit, kosong = App Secret lama tetap dipakai.
            'app_secret' => [$app->exists ? 'nullable' : 'required', 'string', 'max:255'],
            'callback_url' => ['nullable', 'url', 'max:255'],
            'region' => ['required', Rule::in(array_keys(MarketplaceApp::REGIONS))],
            'environment' => ['required', Rule::in(array_keys(MarketplaceApp::ENVIRONMENTS))],
            'is_default' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in([MarketplaceApp::STATUS_ACTIVE, MarketplaceApp::STATUS_INACTIVE])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'app_key.unique' => 'App Key / Partner ID ini sudah terdaftar untuk channel tersebut.',
            'app_key.regex' => 'Partner ID Shopee berupa angka.',
        ], ['app_key' => MarketplaceApp::credentialLabel($channel, 'key'), 'app_secret' => MarketplaceApp::credentialLabel($channel, 'secret')]);
    }

    private function save(MarketplaceApp $app, array $data): void
    {
        if (blank($data['app_secret'] ?? null)) {
            unset($data['app_secret']);
        }
        $data['is_default'] = (bool) ($data['is_default'] ?? false);

        DB::transaction(function () use ($app, $data) {
            $app->fill($data)->save();

            // Hanya satu default per channel. App pertama di channel otomatis jadi default.
            if ($app->is_default) {
                MarketplaceApp::forChannel($app->channel)->whereKeyNot($app->getKey())->update(['is_default' => false]);
            } elseif (! MarketplaceApp::forChannel($app->channel)->where('is_default', true)->exists()) {
                $app->forceFill(['is_default' => true])->save();
            }
        });
    }
}

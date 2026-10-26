<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsApp\Concerns\InWhatsAppArea;
use App\Models\WaApiKey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** API Key per device untuk kirim WA dari aplikasi lain. Secret hanya ditampilkan sekali. */
class ApiKeyController extends Controller
{
    use InWhatsAppArea;

    public function show(Request $request, string $device): View
    {
        $model = $this->device($request, $device)->load('branch:id,name', 'apiKey');

        return view('whatsapp.devices.api-key', [
            'device' => $model,
            'key' => $model->apiKey,
            'logs' => $model->apiKey ? $model->apiKey->logs()->latest('id')->limit(30)->get() : collect(),
        ]);
    }

    public function store(Request $request, string $device): RedirectResponse
    {
        $model = $this->device($request, $device);
        if ($model->apiKey()->exists()) {
            return back()->with('error', 'Perangkat ini sudah punya API key.');
        }

        $key = (new WaApiKey())->forceFill(['branch_id' => $model->branch_id, 'device_id' => $model->id, 'api_key' => WaApiKey::newApiKey(), 'is_active' => true]);
        $secret = $key->issueSecret();
        $key->save();

        return back()->with('secret', $secret)->with('success', 'API key dibuat. Salin Secret Key sekarang, hanya ditampilkan sekali.');
    }

    public function regenerate(Request $request, string $device): RedirectResponse
    {
        $key = $this->device($request, $device)->apiKey()->firstOrFail();
        $secret = $key->issueSecret();
        $key->save();

        return back()->with('secret', $secret)->with('success', 'Secret Key baru dibuat. Yang lama langsung tidak berlaku.');
    }

    public function toggle(Request $request, string $device): RedirectResponse
    {
        $key = $this->device($request, $device)->apiKey()->firstOrFail();
        $key->forceFill(['is_active' => ! $key->is_active])->save();

        return back()->with('success', $key->is_active ? 'API diaktifkan.' : 'API dinonaktifkan, request dari aplikasi lain akan ditolak.');
    }
}

<?php

namespace App\Http\Controllers\Superadmin\Ai;

use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use App\Models\AiSetting;
use App\Services\Ai\AiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Settings > AI: penyedia AI (kunci terenkripsi), satu yang aktif, dan kuota harian user. */
class AiController extends Controller
{
    public function index(): View
    {
        return view('superadmin.ai.index', [
            'providers' => AiProvider::orderByDesc('is_active')->orderBy('name')->get(),
            'setting' => AiSetting::current(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, true);
        $provider = new AiProvider($data);
        $provider->api_key = $data['api_key'];
        $provider->is_active = ! AiProvider::where('is_active', true)->exists();
        $provider->save();

        return back()->with('success', "Penyedia {$provider->name} ditambahkan.".($provider->is_active ? ' Langsung dipakai karena belum ada yang aktif.' : ''));
    }

    public function update(Request $request, AiProvider $provider): RedirectResponse
    {
        $data = $this->validated($request, false);
        $provider->fill($data);

        // Kunci hanya diganti kalau diisi; kosong = tetap pakai yang lama.
        if (filled($data['api_key'] ?? null)) {
            $provider->api_key = $data['api_key'];
        }

        $provider->save();

        return back()->with('success', "Penyedia {$provider->name} diperbarui.");
    }

    public function activate(AiProvider $provider): RedirectResponse
    {
        DB::transaction(function () use ($provider) {
            AiProvider::where('is_active', true)->lockForUpdate()->update(['is_active' => false]);
            $provider->forceFill(['is_active' => true])->save();
        });

        return back()->with('success', "Sekarang fitur AI memakai {$provider->name}.");
    }

    public function test(AiProvider $provider): RedirectResponse
    {
        try {
            (new AiClient($provider))->complete('Balas hanya JSON {"ok":true}.', 'Tes koneksi', 50);
        } catch (RuntimeException $e) {
            return back()->with('error', "{$provider->name}: {$e->getMessage()}");
        }

        return back()->with('success', "{$provider->name} terhubung dengan baik.");
    }

    public function destroy(AiProvider $provider): RedirectResponse
    {
        $provider->delete();

        return back()->with($provider->is_active ? 'warning' : 'success', "Penyedia {$provider->name} dihapus.".($provider->is_active ? ' Fitur AI nonaktif sampai ada penyedia lain yang diaktifkan.' : ''));
    }

    public function quota(Request $request): RedirectResponse
    {
        $data = $request->validate(['daily_quota' => ['required', 'integer', 'min:0', 'max:1000']], [], ['daily_quota' => 'kuota harian']);
        AiSetting::current()->update($data);

        return back()->with('success', 'Kuota AI disimpan: '.$data['daily_quota'].' kali per user per hari.');
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'driver' => ['required', Rule::in(array_keys(AiProvider::DRIVERS))],
            'model' => ['required', 'string', 'max:100'],
            'base_url' => ['nullable', 'url', 'max:255'],
            'api_key' => [$creating ? 'required' : 'nullable', 'string', 'max:500'],
        ], [], ['name' => 'nama', 'driver' => 'penyedia', 'api_key' => 'API key']);
    }
}

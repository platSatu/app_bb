<?php

namespace App\Http\Controllers\Superadmin\Tools;

use App\Http\Controllers\Controller;
use App\Services\Marketing\GooglePlacesClient;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** Superadmin › Tools › Google Places: cari calon pelanggan (mis. tempat kursus) dari Google Maps secara resmi. */
class GooglePlacesController extends Controller
{
    public function index(Request $request, GooglePlacesClient $places): View
    {
        $data = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:100'],
            'page_token' => ['nullable', 'string', 'max:1000'],
            'provider' => ['nullable', 'in:'.implode(',', array_keys(GooglePlacesClient::PROVIDERS))],
        ]);
        // Default: Google kalau key-nya ada, kalau tidak Open Places.
        $provider = $data['provider'] ?? ($places->isConfigured('google') ? 'google' : 'open');

        $keyword = trim((string) ($data['keyword'] ?? ''));
        $location = trim((string) ($data['location'] ?? ''));
        $result = null;
        $error = null;

        if ($keyword !== '') {
            $query = $location !== '' ? "{$keyword} di {$location}" : $keyword;

            try {
                $result = $places->search($query, $data['page_token'] ?? null, $provider, $location);
            } catch (RuntimeException $e) {
                $error = $e->getMessage();
            }
        }

        return view('superadmin.tools.google-places', [
            'keyword' => $keyword,
            'location' => $location,
            'result' => $result,
            'error' => $error,
            'provider' => $provider,
            'configured' => $places->isConfigured($provider),
            'usedToday' => $places->usedToday($provider),
            'dailyLimit' => $places->dailyLimit($provider),
        ]);
    }
}

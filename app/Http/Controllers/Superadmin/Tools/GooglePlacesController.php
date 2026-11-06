<?php

namespace App\Http\Controllers\Superadmin\Tools;

use App\Http\Controllers\Controller;
use App\Services\Marketing\GooglePlacesClient;
use App\Support\XlsxExport;
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
        ]);
        // Sumber data ditentukan menunya: Tools › Google Places atau Tools › Open Places.
        $provider = $request->route('provider') === 'open' ? 'open' : 'google';

        $keyword = trim((string) ($data['keyword'] ?? ''));
        $location = trim((string) ($data['location'] ?? ''));
        $result = null;
        $error = null;

        if ($keyword !== '') {
            $query = $location !== '' ? "{$keyword} di {$location}" : $keyword;

            try {
                $result = $places->search($query, $data['page_token'] ?? null, $provider, $location, ! $request->boolean('all'));
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

    /**
     * Tools › Open Places: unduh tempat yang dicentang ke Excel. Isinya dari hasil yang sedang tampil (tidak
     * mencari ulang, tidak memakai kuota). Hanya Open Places: ketentuan Google melarang menyimpan / mengekspor data Places.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $columns = ['name' => 'Nama', 'category' => 'Kategori', 'address' => 'Alamat', 'city' => 'Kecamatan / Kota', 'province' => 'Provinsi',
            'postal_code' => 'Kode Pos', 'phone' => 'Telepon', 'whatsapp' => 'WhatsApp', 'website' => 'Website', 'lat' => 'Latitude',
            'lon' => 'Longitude', 'distance_km' => 'Jarak (km)', 'maps_url' => 'Link Peta', 'closed' => 'Status'];

        $rows = $this->selected($request)->map(fn (array $row) => collect($columns)->map(fn ($label, $key) => match ($key) {
            'closed' => $row['closed'] ? 'Tutup' : 'Buka',
            default => $row[$key] ?? '',
        })->all());

        return XlsxExport::download('open-places-'.now()->format('Ymd-His'), array_values($columns), $rows);
    }

    /** Tools › Open Places: simpan tempat yang dicentang ke Tools › Prospek. Yang sudah ada (ID tempat / telepon sama) dilewati. */
    public function save(Request $request): \Illuminate\Http\RedirectResponse
    {
        $rows = $this->selected($request);
        $saved = 0;

        foreach ($rows as $row) {
            $exists = \App\Models\Prospect::where('external_id', 'open:'.$row['id'])
                ->when($row['phone'] !== '', fn ($q) => $q->orWhere('phone', $row['phone']))->exists();

            if ($row['id'] === '' || $exists) {
                continue;
            }

            \App\Models\Prospect::create([
                'source' => 'open',
                'external_id' => 'open:'.$row['id'],
                'is_closed' => $row['closed'],
                'created_by' => $request->user()->getKey(),
            ] + array_map(fn ($value) => $value === '' ? null : $value, array_intersect_key($row, array_flip([
                'name', 'category', 'address', 'city', 'province', 'postal_code', 'phone', 'whatsapp', 'website', 'maps_url', 'lat', 'lon',
            ]))));
            $saved++;
        }

        $skipped = $rows->count() - $saved;

        // Tidak kembali ke hasil pencarian: itu akan mencari ulang dan memakai kuota.
        return redirect()->route('superadmin.tools.open-places.index')
            ->with('success', "{$saved} tempat disimpan ke Tools › Prospek".($skipped ? ", {$skipped} sudah ada sebelumnya" : '').'.');
    }

    /**
     * Baris yang dicentang di halaman Open Places (dikirim dari hasil yang tampil). Dibersihkan: hanya kolom
     * yang dikenal, teks dibatasi, angka koordinat harus angka, link harus http(s).
     */
    private function selected(Request $request): \Illuminate\Support\Collection
    {
        $data = $request->validate(['rows' => ['required', 'array', 'max:100'], 'rows.*' => ['string', 'max:3000']]);
        $text = fn ($value, int $max) => is_scalar($value) ? trim(mb_substr((string) $value, 0, $max)) : '';
        $url = fn ($value) => is_string($value) && preg_match('#^https?://#i', $value) ? mb_substr($value, 0, 300) : '';

        return collect($data['rows'])->map(fn (string $json) => (array) json_decode($json, true))
            ->filter(fn (array $row) => filled($row['name'] ?? null))
            ->map(fn (array $row) => [
                'id' => $text($row['id'] ?? '', 140),
                'name' => $text($row['name'], 200),
                'category' => $text($row['category'] ?? '', 150),
                'address' => $text($row['address'] ?? '', 300),
                'city' => $text($row['city'] ?? '', 150),
                'province' => $text($row['province'] ?? '', 100),
                'postal_code' => $text($row['postal_code'] ?? '', 10),
                'phone' => $text($row['phone'] ?? '', 30),
                'whatsapp' => preg_match('/^628\d{6,13}$/', (string) ($row['whatsapp'] ?? '')) ? $row['whatsapp'] : '',
                'website' => $url($row['website'] ?? null),
                'maps_url' => $url($row['maps_url'] ?? null),
                'lat' => is_numeric($row['lat'] ?? null) && abs($row['lat']) <= 90 ? (float) $row['lat'] : '',
                'lon' => is_numeric($row['lon'] ?? null) && abs($row['lon']) <= 180 ? (float) $row['lon'] : '',
                'distance_km' => is_numeric($row['distance_km'] ?? null) ? (float) $row['distance_km'] : '',
                'closed' => ! empty($row['closed']),
            ])->values();
    }
}

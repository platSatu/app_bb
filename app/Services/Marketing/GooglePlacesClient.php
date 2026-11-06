<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Tools › Google Places: cari tempat usaha (mis. tempat kursus). Dua sumber, hasilnya berbentuk sama:
 *  - google: Google Places API (New) Text Search (GOOGLE_PLACES_API_KEY), berbayar setelah kuota gratis.
 *  - open:   Open Places API (OPEN_PLACES_API_KEY), gratis 10.000/bulan, data Overture. Wajib koordinat,
 *            jadi lokasi diubah ke koordinat lewat OpenStreetMap Nominatim (disimpan 30 hari).
 *
 * - API key di .env, tidak pernah dikirim ke browser.
 * - Batas pencarian per hari per sumber (..._DAILY_LIMIT) supaya kuota / tagihan tidak jebol.
 * - Hasil tidak disimpan ke database.
 */
class GooglePlacesClient
{
    public const PROVIDERS = ['google' => 'Google Maps', 'open' => 'Open Places (gratis)'];

    private const ENDPOINT = 'https://places.googleapis.com/v1/places:searchText';

    private const OPEN_ENDPOINT = 'https://api.openplacesapi.com/v1/places';

    /** Sisir area Open Places: 9 titik (tengah + 8 di sekeliling, ±6 km), radius 5 mil (±8 km) per titik. */
    private const OPEN_GRID_STEP = 0.055;

    private const OPEN_GRID_RADIUS_MI = 5;

    private const FIELDS = [
        'places.id',
        'places.displayName',
        'places.formattedAddress',
        'places.nationalPhoneNumber',
        'places.internationalPhoneNumber',
        'places.websiteUri',
        'places.googleMapsUri',
        'places.rating',
        'places.userRatingCount',
        'places.businessStatus',
        'nextPageToken',
    ];

    public function isConfigured(string $provider = 'google'): bool
    {
        return filled(config($this->config($provider).'.key'));
    }

    public function dailyLimit(string $provider = 'google'): int
    {
        return max(1, (int) config($this->config($provider).'.daily_limit', 100));
    }

    public function usedToday(string $provider = 'google'): int
    {
        return (int) Cache::get($this->counterKey($provider), 0);
    }

    /**
     * @return array{places: array<int, array<string, mixed>>, next_page_token: ?string}
     */
    public function search(string $query, ?string $pageToken = null, string $provider = 'google', string $location = ''): array
    {
        if (! $this->isConfigured($provider)) {
            throw new RuntimeException('API key '.self::PROVIDERS[$provider].' belum diisi. Tambahkan '.($provider === 'open' ? 'OPEN_PLACES_API_KEY' : 'GOOGLE_PLACES_API_KEY').' di file .env.');
        }

        if ($this->usedToday($provider) >= $this->dailyLimit($provider)) {
            throw new RuntimeException("Batas pencarian hari ini ({$this->dailyLimit($provider)}x) sudah tercapai. Coba lagi besok.");
        }

        if ($provider === 'open') {
            return $this->searchOpen($query, $location);
        }

        $body = array_filter([
            'textQuery' => $query,
            'languageCode' => 'id',
            'regionCode' => 'ID',
            'pageSize' => 20,
            'pageToken' => $pageToken,
        ]);

        $response = Http::timeout(15)
            ->withHeaders([
                'X-Goog-Api-Key' => config('services.google_places.key'),
                'X-Goog-FieldMask' => implode(',', self::FIELDS),
            ])
            ->post(self::ENDPOINT, $body);

        // Dihitung setelah request terkirim (Google tetap menagih walau hasilnya kosong).
        $this->incrementCounter('google');

        if ($response->failed()) {
            Log::warning('Google Places gagal', ['status' => $response->status(), 'error' => $response->json('error.message')]);

            throw new RuntimeException(match ($response->status()) {
                400 => 'Permintaan ditolak Google. Coba ubah kata kunci pencarian.',
                403 => 'API key ditolak Google. Pastikan Places API (New) sudah aktif dan key-nya benar.',
                429 => 'Kuota Google Places habis. Coba lagi nanti.',
                default => 'Google Places sedang bermasalah. Coba lagi beberapa saat lagi.',
            });
        }

        return [
            'places' => collect($response->json('places', []))->map(fn (array $p) => $this->mapPlace($p))->all(),
            'next_page_token' => $response->json('nextPageToken'),
        ];
    }

    /**
     * Open Places: kata kunci $query di sekitar $location. Satu pencarian Open Places hanya memberi 20 hasil, jadi area
     * disisir 9 titik lalu digabung (tanpa duplikat). Semua hasil ditampilkan; yang nama/kategorinya memuat setiap kata
     * kunci secara utuh ditandai 'match' dan diurutkan paling atas (Open Places juga mencocokkan potongan kata,
     * mis. "les" ikut menangkap "Sales"), supaya tinggal diseleksi.
     */
    private function searchOpen(string $query, string $location): array
    {
        if ($location === '') {
            throw new RuntimeException('Open Places butuh lokasi (mis. Tebet, Jakarta Selatan).');
        }

        $points = [];
        foreach ([0, -1, 1] as $dy) {
            foreach ([0, -1, 1] as $dx) {
                $points[] = [$dy, $dx];
            }
        }

        if ($this->usedToday('open') + count($points) > $this->dailyLimit('open')) {
            throw new RuntimeException('Sisa batas pencarian hari ini tidak cukup untuk menyisir area ('.count($points).' pencarian). Coba lagi besok.');
        }

        [$lat, $lon] = $this->coordinates($location);
        $results = collect();

        foreach ($points as [$dy, $dx]) {
            $response = Http::timeout(10)->withToken((string) config('services.open_places.key'))->acceptJson()->get(self::OPEN_ENDPOINT, [
                'q' => $query, 'limit' => 20, 'radius_mi' => self::OPEN_GRID_RADIUS_MI,
                'lat' => round($lat + $dy * self::OPEN_GRID_STEP, 6), 'lon' => round($lon + $dx * self::OPEN_GRID_STEP, 6),
            ]);
            $this->incrementCounter('open');

            if ($response->failed()) {
                Log::warning('Open Places gagal', ['status' => $response->status(), 'error' => $response->json('error.code')]);

                // Titik pertama gagal = tidak ada hasil sama sekali; titik berikutnya gagal = pakai yang sudah didapat.
                if ($results->isEmpty()) {
                    throw new RuntimeException(match ($response->json('error.code')) {
                        'invalid_api_key', 'unauthenticated' => 'API key Open Places ditolak. Cek OPEN_PLACES_API_KEY.',
                        'quota_exhausted', 'payment_required' => 'Kuota gratis Open Places bulan ini habis.',
                        'rate_limited' => 'Terlalu banyak pencarian ke Open Places. Tunggu sebentar.',
                        'coverage_not_ready' => 'Open Places belum punya data untuk area ini. Coba Google Maps.',
                        default => 'Open Places sedang bermasalah. Coba lagi beberapa saat lagi.',
                    });
                }

                break;
            }

            $results = $results->merge($response->json('results', []));
        }

        $words = array_filter(preg_split('/\s+/u', mb_strtolower($query)));

        return [
            'places' => $results->unique('place_id')->map(function (array $p) use ($words, $lat, $lon) {
                $text = mb_strtolower(($p['name'] ?? '').' '.str_replace('_', ' ', implode(' ', [$p['category'] ?? '', ...($p['categories'] ?? [])])));

                return $this->mapPlace([
                    'id' => $p['place_id'] ?? null,
                    'displayName' => ['text' => $p['name'] ?? '-'],
                    'formattedAddress' => $p['address']['formatted'] ?? null,
                    'nationalPhoneNumber' => $p['phone'] ?? null,
                    'websiteUri' => $p['website'] ?? null,
                    'googleMapsUri' => isset($p['lat'], $p['lon']) ? "https://www.google.com/maps/search/?api=1&query={$p['lat']},{$p['lon']}" : null,
                    'businessStatus' => ($p['operating_status'] ?? null) === 'closed' ? 'CLOSED_PERMANENTLY' : null,
                ]) + [
                    'match' => collect($words)->every(fn ($word) => preg_match('/(?<![\p{L}\p{N}])'.preg_quote($word, '/').'(?![\p{L}\p{N}])/u', $text)),
                    'category' => ucfirst(str_replace('_', ' ', (string) ($p['category'] ?? ''))) ?: null,
                    'city' => $p['address']['locality'] ?? null,
                    'province' => $p['address']['region'] ?? null,
                    'postal_code' => $p['address']['postal_code'] ?? null,
                    'lat' => $p['lat'] ?? null,
                    'lon' => $p['lon'] ?? null,
                    // Jarak dari lokasi yang diketik (bukan dari titik sisir), perkiraan garis lurus.
                    'distance_km' => isset($p['lat'], $p['lon'])
                        ? round(111.32 * sqrt(($p['lat'] - $lat) ** 2 + (($p['lon'] - $lon) * cos(deg2rad($lat))) ** 2), 1) : null,
                ];
            })->sortBy([['match', 'desc'], ['distance_km', 'asc']])->values()->all(),
            'next_page_token' => null,
        ];
    }

    /** Nama lokasi -> [lat, lon] lewat OpenStreetMap Nominatim (gratis, maks 1 permintaan/detik), disimpan 30 hari. */
    private function coordinates(string $location): array
    {
        $point = Cache::remember('tools:places:geo:'.md5(mb_strtolower($location)), now()->addDays(30), function () use ($location) {
            $result = Http::timeout(10)->withHeaders(['User-Agent' => 'bizbos-places-tool/1.0 ('.config('app.url').')'])
                ->get('https://nominatim.openstreetmap.org/search', ['q' => $location, 'format' => 'json', 'limit' => 1, 'countrycodes' => 'id'])
                ->json('0');

            return isset($result['lat'], $result['lon']) ? [(float) $result['lat'], (float) $result['lon']] : null;
        });

        if (! $point) {
            Cache::forget('tools:places:geo:'.md5(mb_strtolower($location)));

            throw new RuntimeException('Lokasi "'.$location.'" tidak ditemukan. Tulis lebih lengkap, mis. "Tebet, Jakarta Selatan".');
        }

        return $point;
    }

    private function mapPlace(array $p): array
    {
        $international = $p['internationalPhoneNumber'] ?? null;
        $phone = $p['nationalPhoneNumber'] ?? $international;
        // 0812… / +62 812… -> 62812…
        $digits = $phone ? preg_replace('/^0/', '62', preg_replace('/\D+/', '', $phone)) : null;

        return [
            'id' => $p['id'] ?? null,
            'name' => $p['displayName']['text'] ?? '-',
            'address' => $p['formattedAddress'] ?? null,
            'phone' => $phone,
            // Nomor HP Indonesia (62 8xx) kemungkinan besar bisa WhatsApp; nomor kantor (021, dll) tidak.
            'whatsapp' => $digits && str_starts_with($digits, '628') ? $digits : null,
            'website' => $this->safeUrl($p['websiteUri'] ?? null),
            'maps_url' => $this->safeUrl($p['googleMapsUri'] ?? null),
            'rating' => $p['rating'] ?? null,
            'rating_count' => $p['userRatingCount'] ?? 0,
            'closed' => in_array($p['businessStatus'] ?? null, ['CLOSED_PERMANENTLY', 'CLOSED_TEMPORARILY'], true),
        ];
    }

    /** Hanya link http/https yang ditampilkan sebagai tombol. */
    private function safeUrl(?string $url): ?string
    {
        return $url && preg_match('#^https?://#i', $url) ? $url : null;
    }

    private function config(string $provider): string
    {
        return $provider === 'open' ? 'services.open_places' : 'services.google_places';
    }

    private function counterKey(string $provider): string
    {
        // Kunci Google tetap sama seperti sebelumnya (hitungan hari ini tidak ter-reset saat deploy).
        return 'tools:'.($provider === 'open' ? 'open-places' : 'google-places').':count:'.now()->toDateString();
    }

    private function incrementCounter(string $provider): void
    {
        $key = $this->counterKey($provider);
        Cache::add($key, 0, now()->endOfDay());
        Cache::increment($key);
    }
}

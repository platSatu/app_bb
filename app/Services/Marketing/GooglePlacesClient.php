<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Tools › Google Places: cari tempat usaha (mis. tempat kursus) lewat Google Places API (New) Text Search.
 *
 * - API key di .env (GOOGLE_PLACES_API_KEY), tidak pernah dikirim ke browser.
 * - Batas pencarian per hari (GOOGLE_PLACES_DAILY_LIMIT) supaya tagihan Google tidak membengkak.
 * - Hasil tidak disimpan ke database (sesuai ketentuan Google); yang boleh disimpan permanen hanya place_id.
 */
class GooglePlacesClient
{
    private const ENDPOINT = 'https://places.googleapis.com/v1/places:searchText';

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

    public function isConfigured(): bool
    {
        return filled(config('services.google_places.key'));
    }

    public function dailyLimit(): int
    {
        return max(1, (int) config('services.google_places.daily_limit', 100));
    }

    public function usedToday(): int
    {
        return (int) Cache::get($this->counterKey(), 0);
    }

    /**
     * @return array{places: array<int, array<string, mixed>>, next_page_token: ?string}
     */
    public function search(string $query, ?string $pageToken = null): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('API key Google Places belum diisi. Tambahkan GOOGLE_PLACES_API_KEY di file .env.');
        }

        if ($this->usedToday() >= $this->dailyLimit()) {
            throw new RuntimeException("Batas pencarian hari ini ({$this->dailyLimit()}x) sudah tercapai. Coba lagi besok.");
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
        $this->incrementCounter();

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

    private function mapPlace(array $p): array
    {
        $international = $p['internationalPhoneNumber'] ?? null;
        $digits = $international ? preg_replace('/\D+/', '', $international) : null;

        return [
            'id' => $p['id'] ?? null,
            'name' => $p['displayName']['text'] ?? '-',
            'address' => $p['formattedAddress'] ?? null,
            'phone' => $p['nationalPhoneNumber'] ?? $international,
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

    private function counterKey(): string
    {
        return 'tools:google-places:count:'.now()->toDateString();
    }

    private function incrementCounter(): void
    {
        $key = $this->counterKey();
        Cache::add($key, 0, now()->endOfDay());
        Cache::increment($key);
    }
}

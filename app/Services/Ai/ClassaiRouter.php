<?php

namespace App\Services\Ai;

use App\Models\AiProvider;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Info akun Classai Router (https://router.classai.id/dashboard/docs), dipanggil dari server saja:
 * - models(): GET /v1/models, model yang sedang aktif untuk key ini.
 * - account(): GET /v1/account, saldo kredit & paket yang berjalan (tidak ditagih).
 * - ping(): satu jawaban sangat pendek untuk mengukur kecepatan model (ditagih, tapi hanya beberapa token).
 * API key tidak pernah ikut dikirim ke browser; hasil daftar model disimpan sebentar di cache.
 */
class ClassaiRouter
{
    private const MODELS_TTL = 600;

    private const ACCOUNT_TTL = 60;

    /** @return list<array{id: string, name: string, context: int|string|null}> */
    public function models(AiProvider $provider, bool $fresh = false): array
    {
        $this->ensureClassai($provider);
        $key = $this->cacheKey($provider, 'models');

        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, self::MODELS_TTL, function () use ($provider) {
            $items = $this->get($provider, '/models')->json('data');

            if (! is_array($items)) {
                throw new RuntimeException('Daftar model dari Classai tidak bisa dibaca.');
            }

            $models = [];

            foreach ($items as $item) {
                $id = is_array($item) ? ($item['id'] ?? null) : null;

                // Hanya ID yang aman dipakai sebagai nama model (dicek juga saat disimpan/dikirim).
                if (! is_string($id) || ! preg_match(AiProvider::MODEL_PATTERN, $id)) {
                    continue;
                }

                $name = $item['name'] ?? $item['display_name'] ?? null;
                $context = $item['context_length'] ?? $item['context_window'] ?? $item['context'] ?? null;

                $models[$id] = [
                    'id' => $id,
                    'name' => is_string($name) && $name !== '' ? mb_substr($name, 0, 100) : $id,
                    'context' => is_int($context) || is_string($context) ? $context : null,
                ];
            }

            return array_values($models);
        });
    }

    /** @return list<string> */
    public function modelIds(AiProvider $provider): array
    {
        return array_column($this->models($provider), 'id');
    }

    /**
     * Saldo akun. Bentuk balasan resmi: balance {credits, label, idr}; paket dibaca seadanya karena
     * dokumentasinya belum merinci kolomnya.
     *
     * @return array{credits: int|float|null, label: ?string, idr: int|float|string|null, packages: list<array{name: string, detail: ?string}>}
     */
    public function account(AiProvider $provider, bool $fresh = false): array
    {
        $this->ensureClassai($provider);
        $key = $this->cacheKey($provider, 'account');

        if ($fresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, self::ACCOUNT_TTL, function () use ($provider) {
            $json = $this->get($provider, '/account')->json();
            $balance = is_array($json['balance'] ?? null) ? $json['balance'] : [];
            $packages = [];

            foreach ((array) ($json['packages'] ?? $balance['packages'] ?? []) as $package) {
                if (! is_array($package)) {
                    continue;
                }

                $name = $package['name'] ?? $package['label'] ?? $package['package'] ?? $package['model'] ?? null;
                $detail = $package['remaining_label'] ?? $package['remaining'] ?? $package['tokens_remaining']
                    ?? $package['remaining_tokens'] ?? $package['tokens'] ?? null;
                $until = $package['expires_at'] ?? $package['ends_at'] ?? null;

                if (! is_scalar($name)) {
                    continue;
                }

                $parts = array_filter([
                    is_scalar($detail) ? 'sisa '.$this->plain($detail) : null,
                    is_scalar($until) ? 's.d. '.$this->plain($until) : null,
                ]);

                $packages[] = ['name' => $this->plain($name), 'detail' => $parts ? implode(' · ', $parts) : null];
            }

            return [
                'credits' => is_numeric($balance['credits'] ?? null) ? $balance['credits'] + 0 : null,
                'label' => is_scalar($balance['label'] ?? null) ? $this->plain($balance['label']) : null,
                'idr' => is_scalar($balance['idr'] ?? null) ? $balance['idr'] : null,
                'packages' => $packages,
            ];
        });
    }

    /** Waktu (milidetik) sampai model menjawab satu kata. */
    public function ping(AiProvider $provider, string $model): int
    {
        $start = hrtime(true);
        (new AiClient($provider))->withModel($model)->chat('Balas hanya dengan kata: ok', [['role' => 'user', 'content' => 'tes']], 8);

        return (int) round((hrtime(true) - $start) / 1_000_000);
    }

    private function get(AiProvider $provider, string $path): Response
    {
        try {
            $response = Http::timeout(10)->acceptJson()->withToken($provider->api_key)->get($provider->baseUrl().$path);
        } catch (Throwable $e) {
            report($e);

            throw new RuntimeException('Classai Router sedang tidak bisa dihubungi. Coba lagi sebentar lagi.');
        }

        if ($response->failed()) {
            report(new RuntimeException('Classai '.$path.' HTTP '.$response->status().': '.mb_substr($response->body(), 0, 300)));

            throw new RuntimeException(AiClient::errorMessage($response->status()));
        }

        return $response;
    }

    private function ensureClassai(AiProvider $provider): void
    {
        if (! $provider->isClassai()) {
            throw new RuntimeException('Fitur ini hanya untuk penyedia Classai Router.');
        }
    }

    /** Kunci cache ikut berubah saat penyedia diubah (mis. API key diganti). */
    private function cacheKey(AiProvider $provider, string $what): string
    {
        return 'classai:'.$what.':'.$provider->getKey().':'.optional($provider->updated_at)->timestamp;
    }

    private function plain(mixed $value): string
    {
        return mb_substr(trim(strip_tags((string) $value)), 0, 120);
    }
}

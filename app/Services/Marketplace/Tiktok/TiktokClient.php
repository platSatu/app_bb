<?php

namespace App\Services\Marketplace\Tiktok;

use App\Models\MarketplaceApp;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\MarketplaceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pemanggil TikTok Shop Open API (versi 202309+).
 * - Parameter umum di query: app_key, timestamp (10 digit), sign, shop_cipher (API toko).
 * - Access token di header x-tts-access-token (tidak ikut di-sign).
 * - Sign = hex HMAC-SHA256(app_secret, app_secret + path + {key}{value} terurut (tanpa sign & access_token) + body + app_secret).
 *   Body ikut di-sign kecuali multipart; yang di-sign adalah byte body yang persis dikirim.
 * Dokumentasi: https://partner.tiktokshop.com/docv2/page/sign-your-api-request
 */
class TiktokClient
{
    public const API_HOST = 'https://open-api.tiktokglobalshop.com';

    public const AUTH_HOST = 'https://auth.tiktok-shops.com';

    /** Halaman otorisasi seller per pasar (US punya domain sendiri). */
    public const AUTHORIZE_HOSTS = [
        'us' => 'https://services.us.tiktokshop.com',
        'row' => 'https://services.tiktokshop.com',
    ];

    public static function sign(string $path, array $query, string $body, string $secret): string
    {
        unset($query['sign'], $query['access_token']);
        ksort($query, SORT_STRING);

        $base = $path;
        foreach ($query as $key => $value) {
            $base .= $key.$value;
        }

        return hash_hmac('sha256', $secret.$base.$body.$secret, $secret);
    }

    /** Token get/refresh: GET ke auth.tiktok-shops.com dengan app_key & app_secret di query (tanpa sign). */
    public function auth(MarketplaceApp $app, string $path, array $params): array
    {
        $query = ['app_key' => $app->app_key, 'app_secret' => (string) $app->app_secret] + $params;

        return $this->handle($path, fn () => Http::timeout(30)->connectTimeout(10)->acceptJson()->get(self::AUTH_HOST.$path, $query));
    }

    /** API tingkat aplikasi (mis. Get Authorized Shops): tanpa shop_cipher. */
    public function appGet(MarketplaceApp $app, string $accessToken, string $path, array $query = []): array
    {
        return $this->send($app, $accessToken, 'GET', $path, $query, null);
    }

    public function shopGet(MarketplaceStore $store, string $path, array $query = []): array
    {
        return $this->send($store->app, $store->access_token, 'GET', $path, $query + $this->shopQuery($store), null);
    }

    public function shopPost(MarketplaceStore $store, string $path, array|object $body, array $query = []): array
    {
        return $this->send($store->app, $store->access_token, 'POST', $path, $query + $this->shopQuery($store), $body);
    }

    /** Upload file (multipart): body tidak ikut di-sign. */
    public function shopUpload(MarketplaceStore $store, string $path, string $field, string $contents, string $filename, array $fields = []): array
    {
        $query = $this->signedQuery($store->app, $path, $this->shopQuery($store), '');

        return $this->handle($path, fn () => Http::timeout(60)->connectTimeout(10)->acceptJson()
            ->withHeaders(['x-tts-access-token' => (string) $store->access_token])
            ->attach($field, $contents, $filename)
            ->post(self::API_HOST.$path.'?'.http_build_query($query), $fields));
    }

    private function send(MarketplaceApp $app, string $accessToken, string $method, string $path, array $query, array|object|null $body): array
    {
        // Body di-encode sekali, lalu byte yang sama dipakai untuk sign & dikirim.
        $raw = $body === null ? '' : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $url = self::API_HOST.$path.'?'.http_build_query($this->signedQuery($app, $path, $query, $raw));

        return $this->handle($path, function () use ($method, $url, $raw, $accessToken) {
            $request = Http::timeout(40)->connectTimeout(10)->acceptJson()
                ->withHeaders(['x-tts-access-token' => $accessToken, 'content-type' => 'application/json']);

            return $method === 'POST' ? $request->withBody($raw, 'application/json')->post($url) : $request->get($url);
        });
    }

    private function signedQuery(MarketplaceApp $app, string $path, array $query, string $body): array
    {
        $query = array_filter($query, fn ($v) => $v !== null && $v !== '') + [
            'app_key' => $app->app_key,
            'timestamp' => time(),
        ];
        $query['sign'] = self::sign($path, $query, $body, (string) $app->app_secret);

        return $query;
    }

    private function shopQuery(MarketplaceStore $store): array
    {
        return ['shop_cipher' => data_get($store->meta, 'shop_cipher')];
    }

    private function handle(string $path, callable $call): array
    {
        try {
            /** @var Response $response */
            $response = $call();
        } catch (ConnectionException $e) {
            throw new MarketplaceException('Tidak bisa menghubungi TikTok Shop: '.$e->getMessage());
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new MarketplaceException('Jawaban TikTok Shop tidak bisa dibaca (HTTP '.$response->status().').');
        }

        $code = (string) ($body['code'] ?? '0');

        if ($code !== '0' || $response->failed()) {
            $message = (string) ($body['message'] ?? 'Permintaan ditolak TikTok Shop.');

            Log::warning('TikTok Shop API error', ['path' => $path, 'code' => $code, 'message' => $message, 'request_id' => $body['request_id'] ?? null]);

            throw new MarketplaceException("TikTok Shop ({$code}): {$message}", $code, $body);
        }

        return $body;
    }
}

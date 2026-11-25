<?php

namespace App\Services\Marketplace\Shopee;

use App\Models\MarketplaceApp;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\MarketplaceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pemanggil Shopee Open API v2.
 * - Parameter umum (partner_id, timestamp, sign, access_token, shop_id) selalu di query string.
 * - GET: parameter request ikut di query. POST: parameter request di body JSON (upload gambar: multipart).
 * - Sign = hex huruf kecil dari HMAC-SHA256(partner_key, partner_id + path + timestamp [+ access_token + shop_id]).
 * Dokumentasi: https://open.shopee.com/developer-guide/16 & /developer-guide/20
 */
class ShopeeClient
{
    public const API_HOSTS = [
        'live' => 'https://partner.shopeemobile.com',
        'sandbox' => 'https://openplatform.sandbox.test-stable.shopee.sg',
    ];

    public const AUTH_HOSTS = [
        'live' => 'https://open.shopee.com',
        'sandbox' => 'https://open.sandbox.test-stable.shopee.com',
    ];

    public function apiHost(MarketplaceApp $app): string
    {
        return self::API_HOSTS[$app->isSandbox() ? 'sandbox' : 'live'];
    }

    public function authHost(MarketplaceApp $app): string
    {
        return self::AUTH_HOSTS[$app->isSandbox() ? 'sandbox' : 'live'];
    }

    public static function sign(string $partnerKey, string $baseString): string
    {
        return hash_hmac('sha256', $baseString, $partnerKey);
    }

    /** Public API (tanpa toko): token, upload gambar. */
    public function publicPost(MarketplaceApp $app, string $path, array $body): array
    {
        return $this->send($app, 'POST', $path, [], $body, null);
    }

    /** Shop API (butuh access_token & shop_id). */
    public function shopGet(MarketplaceStore $store, string $path, array $query = []): array
    {
        return $this->send($store->app, 'GET', $path, $query, null, $store);
    }

    public function shopPost(MarketplaceStore $store, string $path, array $body): array
    {
        return $this->send($store->app, 'POST', $path, [], $body, $store);
    }

    /** Upload file gambar (multipart). v2.media_space.upload_image adalah Public API. */
    public function uploadImage(MarketplaceApp $app, string $contents, string $filename): array
    {
        $path = '/api/v2/media_space/upload_image';
        $url = $this->apiHost($app).$path.'?'.$this->commonQuery($app, $path, null);

        return $this->handle($path, fn () => Http::timeout(60)->connectTimeout(10)->acceptJson()
            ->attach('image', $contents, $filename)
            ->post($url, ['scene' => 'normal']));
    }

    private function send(MarketplaceApp $app, string $method, string $path, array $query, ?array $body, ?MarketplaceStore $store): array
    {
        $url = $this->apiHost($app).$path.'?'.$this->commonQuery($app, $path, $store).($query ? '&'.self::buildQuery($query) : '');

        return $this->handle($path, function () use ($method, $url, $body) {
            $request = Http::timeout(40)->connectTimeout(10)->acceptJson();

            return $method === 'POST' ? $request->asJson()->post($url, $body ?? []) : $request->get($url);
        });
    }

    private function commonQuery(MarketplaceApp $app, string $path, ?MarketplaceStore $store): string
    {
        $partnerId = (int) $app->app_key;
        $timestamp = time();
        $base = $partnerId.$path.$timestamp;
        $params = ['partner_id' => $partnerId, 'timestamp' => $timestamp];

        if ($store) {
            $base .= $store->access_token.$store->external_id;
            $params += ['access_token' => $store->access_token, 'shop_id' => (int) $store->external_id];
        }

        $params['sign'] = self::sign((string) $app->app_secret, $base);

        return self::buildQuery($params);
    }

    /** Array ditulis berulang: item_status=NORMAL&item_status=UNLIST (format yang diminta Shopee). */
    public static function buildQuery(array $params): string
    {
        $parts = [];
        foreach ($params as $key => $value) {
            foreach ((array) $value as $item) {
                if ($item === null) {
                    continue;
                }
                $parts[] = rawurlencode((string) $key).'='.rawurlencode(is_bool($item) ? ($item ? 'true' : 'false') : (string) $item);
            }
        }

        return implode('&', $parts);
    }

    private function handle(string $path, callable $call): array
    {
        try {
            /** @var Response $response */
            $response = $call();
        } catch (ConnectionException $e) {
            throw new MarketplaceException('Tidak bisa menghubungi Shopee: '.$e->getMessage());
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new MarketplaceException('Jawaban Shopee tidak bisa dibaca (HTTP '.$response->status().').');
        }

        $error = (string) ($body['error'] ?? '');

        if ($error !== '' || $response->failed()) {
            $message = (string) ($body['message'] ?? 'Permintaan ditolak Shopee.');

            Log::warning('Shopee API error', ['path' => $path, 'error' => $error, 'message' => $message, 'request_id' => $body['request_id'] ?? null]);

            throw new MarketplaceException('Shopee ('.($error ?: 'HTTP '.$response->status()).'): '.$message, $error ?: null, $body);
        }

        return $body;
    }
}

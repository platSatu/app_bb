<?php

namespace App\Services\Marketplace\Lazada;

use App\Models\MarketplaceApp;
use App\Services\Marketplace\MarketplaceException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pemanggil Lazada Open Platform (REST + signature HMAC-SHA256).
 * Dokumentasi: https://open.lazada.com/apps/doc/doc?nodeId=10450&docId=108068
 */
class LazadaClient
{
    public const AUTH_URL = 'https://auth.lazada.com';

    public const API_URLS = [
        'id' => 'https://api.lazada.co.id/rest',
        'my' => 'https://api.lazada.com.my/rest',
        'sg' => 'https://api.lazada.sg/rest',
        'th' => 'https://api.lazada.co.th/rest',
        'vn' => 'https://api.lazada.vn/rest',
        'ph' => 'https://api.lazada.com.ph/rest',
    ];

    public function apiUrl(?string $country, MarketplaceApp $app): string
    {
        return self::API_URLS[strtolower((string) $country)] ?? self::API_URLS[$app->region] ?? self::API_URLS['id'];
    }

    /** Endpoint otorisasi (token create/refresh) memakai host auth.lazada.com. */
    public function auth(MarketplaceApp $app, string $api, array $params): array
    {
        return $this->send('GET', self::AUTH_URL.'/rest', $app, $api, $params, null);
    }

    public function get(MarketplaceApp $app, string $baseUrl, string $api, array $params = [], ?string $accessToken = null): array
    {
        return $this->send('GET', $baseUrl, $app, $api, $params, $accessToken);
    }

    public function post(MarketplaceApp $app, string $baseUrl, string $api, array $params = [], ?string $accessToken = null): array
    {
        return $this->send('POST', $baseUrl, $app, $api, $params, $accessToken);
    }

    /**
     * Signature: urutkan parameter (selain sign) berdasar nama, gabungkan nama+nilai,
     * awali dengan nama API, lalu HMAC-SHA256 dengan App Secret -> hex huruf besar.
     */
    public static function sign(string $api, array $params, string $secret): string
    {
        unset($params['sign']);
        ksort($params, SORT_STRING);

        $base = $api;
        foreach ($params as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $base .= $key.$value;
        }

        return strtoupper(hash_hmac('sha256', $base, $secret));
    }

    private function send(string $method, string $baseUrl, MarketplaceApp $app, string $api, array $params, ?string $accessToken): array
    {
        $params = array_map(fn ($v) => is_bool($v) ? ($v ? 'true' : 'false') : (is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $v), $params);
        $params = array_filter($params, fn ($v) => $v !== null);

        $params += [
            'app_key' => $app->app_key,
            'timestamp' => (string) (int) floor(microtime(true) * 1000),
            'sign_method' => 'sha256',
        ];
        if ($accessToken) {
            $params['access_token'] = $accessToken;
        }
        $params['sign'] = self::sign($api, $params, (string) $app->app_secret);

        $url = rtrim($baseUrl, '/').$api;

        try {
            $request = Http::timeout(40)->connectTimeout(10)->acceptJson();
            $response = $method === 'POST' ? $request->asForm()->post($url, $params) : $request->get($url, $params);
        } catch (ConnectionException $e) {
            throw new MarketplaceException('Tidak bisa menghubungi Lazada: '.$e->getMessage());
        }

        $body = $response->json();

        if (! is_array($body)) {
            throw new MarketplaceException('Jawaban Lazada tidak bisa dibaca (HTTP '.$response->status().').');
        }

        $code = (string) ($body['code'] ?? '0');

        if ($code !== '0') {
            $message = (string) ($body['message'] ?? $body['msg'] ?? 'Permintaan ditolak Lazada.');
            $detail = collect($body['detail'] ?? [])->map(fn ($d) => is_array($d) ? trim(($d['field'] ?? '').' '.($d['message'] ?? '')) : (string) $d)->filter()->implode('; ');

            Log::warning('Lazada API error', ['api' => $api, 'code' => $code, 'message' => $message, 'request_id' => $body['request_id'] ?? null]);

            throw new MarketplaceException("Lazada ({$code}): {$message}".($detail ? " — {$detail}" : ''), $code, $body);
        }

        return $body;
    }
}

<?php

namespace App\Services\Marketplace\Lazada;

use App\Models\MarketplaceApp;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Contracts\MarketplaceChannel;
use App\Services\Marketplace\MarketplaceException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/** Driver Lazada: otorisasi seller, token, produk, kategori, gambar & unggah produk. */
class LazadaChannel implements MarketplaceChannel
{
    private const LANGUAGES = ['id' => 'id_ID', 'my' => 'ms_MY', 'sg' => 'en_SG', 'th' => 'th_TH', 'vn' => 'vi_VN', 'ph' => 'fil_PH'];

    public function __construct(private readonly LazadaClient $client) {}

    public function key(): string
    {
        return 'lazada';
    }

    public function authorizeUrl(MarketplaceApp $app, string $state): string
    {
        return LazadaClient::AUTH_URL.'/oauth/authorize?'.http_build_query([
            'response_type' => 'code',
            'force_auth' => 'true',
            'redirect_uri' => $app->callbackUrl(),
            'client_id' => $app->app_key,
            'state' => $state,
        ]);
    }

    public function exchangeCode(MarketplaceApp $app, string $code): array
    {
        $body = $this->client->auth($app, '/auth/token/create', ['code' => $code]);

        if (empty($body['access_token'])) {
            throw new MarketplaceException('Lazada tidak mengirim access token. Coba otorisasi ulang.');
        }

        $country = strtolower((string) ($body['country'] ?? $app->region));
        $users = $body['country_user_info'] ?? $body['country_user_info_list'] ?? [];
        $user = collect($users)->firstWhere('country', $country) ?? ($users[0] ?? []);

        $store = [
            'external_id' => (string) ($user['seller_id'] ?? $user['user_id'] ?? $body['account'] ?? ''),
            'name' => null,
            'country' => $country,
            'account' => $body['account'] ?? null,
            'short_code' => $user['short_code'] ?? null,
            'access_token' => $body['access_token'],
            'refresh_token' => $body['refresh_token'] ?? null,
            'access_expires_at' => $this->expiresAt($body['expires_in'] ?? null),
            'refresh_expires_at' => $this->expiresAt($body['refresh_expires_in'] ?? null),
            'meta' => ['country_user_info' => $users, 'account_platform' => $body['account_platform'] ?? null],
        ];

        if ($store['external_id'] === '') {
            throw new MarketplaceException('Data seller dari Lazada tidak lengkap. Coba otorisasi ulang.');
        }

        // Nama toko (Seller API). Kalau app belum punya izin Seller API, cukup pakai email/short code.
        try {
            $seller = $this->client->get($app, $this->client->apiUrl($country, $app), '/seller/get', [], $body['access_token']);
            $store['name'] = Arr::get($seller, 'data.name') ?: Arr::get($seller, 'data.name_company');
        } catch (MarketplaceException) {
        }

        return $store;
    }

    public function refreshToken(MarketplaceStore $store): void
    {
        if (! $store->canRefresh()) {
            throw new MarketplaceException('Refresh token toko sudah habis. Seller perlu otorisasi ulang.');
        }

        try {
            $body = $this->client->auth($store->app, '/auth/token/refresh', ['refresh_token' => $store->refresh_token]);
        } catch (MarketplaceException $e) {
            $store->forceFill(['status' => MarketplaceStore::STATUS_EXPIRED, 'status_reason' => $e->getMessage()])->save();

            throw $e;
        }

        $store->forceFill([
            'access_token' => $body['access_token'],
            'refresh_token' => $body['refresh_token'] ?? $store->refresh_token,
            'access_expires_at' => $this->expiresAt($body['expires_in'] ?? null),
            'refresh_expires_at' => $this->expiresAt($body['refresh_expires_in'] ?? null) ?? $store->refresh_expires_at,
            'status' => MarketplaceStore::STATUS_CONNECTED,
            'status_reason' => null,
        ])->save();
    }

    public function fetchProducts(MarketplaceStore $store, int $offset, int $limit): array
    {
        $body = $this->call($store, 'GET', '/products/get', ['filter' => 'all', 'offset' => $offset, 'limit' => min(50, $limit)]);

        return [
            'total' => (int) Arr::get($body, 'data.total_products', 0),
            'items' => collect(Arr::get($body, 'data.products', []))->map(fn (array $p) => $this->normalizeProduct($p))->all(),
        ];
    }

    /** Pohon kategori Lazada diratakan jadi daftar kategori paling akhir (leaf) beserta jalurnya. Di-cache 12 jam. */
    public function leafCategories(MarketplaceStore $store): array
    {
        $key = 'marketplace:lazada:categories:'.$store->app_id.':'.$store->country;

        return Cache::remember($key, now()->addHours(12), function () use ($store) {
            $body = $this->call($store, 'GET', '/category/tree/get', ['language_code' => $this->language($store)]);
            $leaves = [];

            $walk = function (array $nodes, array $path) use (&$walk, &$leaves) {
                foreach ($nodes as $node) {
                    $trail = [...$path, (string) ($node['name'] ?? '')];
                    if (! empty($node['children'])) {
                        $walk($node['children'], $trail);
                    } elseif ($node['leaf'] ?? true) {
                        $leaves[] = ['id' => (string) $node['category_id'], 'name' => (string) ($node['name'] ?? ''), 'path' => implode(' › ', $trail)];
                    }
                }
            };
            $walk(Arr::get($body, 'data', []), []);

            return $leaves;
        });
    }

    /** Atribut kategori. Dipisah: atribut produk (Attributes) dan atribut SKU. */
    public function categoryAttributes(MarketplaceStore $store, string $categoryId): array
    {
        $key = 'marketplace:lazada:attributes:'.$store->app_id.':'.$store->country.':'.$categoryId;

        return Cache::remember($key, now()->addHours(12), function () use ($store, $categoryId) {
            $body = $this->call($store, 'GET', '/category/attributes/get', ['primary_category_id' => $categoryId, 'language_code' => $this->language($store)]);

            return collect(Arr::get($body, 'data', []))->map(fn (array $a) => [
                'name' => (string) $a['name'],
                'label' => (string) ($a['label'] ?? $a['name']),
                'input_type' => (string) ($a['input_type'] ?? 'text'),
                'attribute_type' => (string) ($a['attribute_type'] ?? 'normal'),
                'is_mandatory' => (string) ($a['is_mandatory'] ?? '0') === '1',
                'is_sale_prop' => (string) ($a['is_sale_prop'] ?? '0') === '1',
                'options' => collect($a['options'] ?? [])->map(fn ($o) => (string) ($o['name'] ?? ''))->filter()->values()->all(),
            ])->values()->all();
        });
    }

    public function categorySuggestions(MarketplaceStore $store, string $productName, ?string $imageUrl = null): array
    {
        $body = $this->call($store, 'GET', '/product/category/suggestion/get', array_filter(['product_name' => $productName, 'image_url' => $imageUrl]));

        return collect(Arr::get($body, 'data.categorySuggestions', []))->map(fn ($c) => [
            'id' => (string) ($c['categoryId'] ?? ''),
            'name' => (string) ($c['categoryName'] ?? ''),
            'path' => (string) ($c['categoryPath'] ?? $c['categoryName'] ?? ''),
        ])->filter(fn ($c) => $c['id'] !== '')->values()->all();
    }

    /** Brand Lazada per halaman (API tidak menyediakan pencarian nama). */
    public function brands(MarketplaceStore $store, int $startRow = 0, int $pageSize = 200): array
    {
        $body = $this->call($store, 'GET', '/category/brands/query', ['startRow' => $startRow, 'pageSize' => min(200, $pageSize)]);

        return [
            'total' => (int) Arr::get($body, 'data.total_record', 0),
            'items' => collect(Arr::get($body, 'data.module', []))->map(fn ($b) => ['id' => (string) $b['brand_id'], 'name' => (string) ($b['name_en'] ?? $b['name'])])->all(),
        ];
    }

    /** Salin gambar dari URL publik ke server gambar Lazada; deskripsi & foto produk wajib memakai URL Lazada. */
    public function migrateImage(MarketplaceStore $store, string $url): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" ?><Request><Image><Url>'.htmlspecialchars($url, ENT_XML1).'</Url></Image></Request>';
        $body = $this->call($store, 'POST', '/image/migrate', ['payload' => $xml]);

        $migrated = (string) Arr::get($body, 'data.image.url', '');
        if ($migrated === '') {
            throw new MarketplaceException('Gambar gagal disalin ke Lazada: '.$url);
        }

        return $migrated;
    }

    /** @return array{item_id: string, sku_list: array} */
    public function createProduct(MarketplaceStore $store, array $payload): array
    {
        $body = $this->call($store, 'POST', '/product/create', ['payload' => $payload]);

        return ['item_id' => (string) Arr::get($body, 'data.item_id', ''), 'sku_list' => Arr::get($body, 'data.sku_list', []), 'raw' => $body];
    }

    private function call(MarketplaceStore $store, string $method, string $api, array $params): array
    {
        $baseUrl = $this->client->apiUrl($store->country, $store->app);

        return $method === 'POST'
            ? $this->client->post($store->app, $baseUrl, $api, $params, $store->access_token)
            : $this->client->get($store->app, $baseUrl, $api, $params, $store->access_token);
    }

    private function normalizeProduct(array $p): array
    {
        $sku = $p['skus'][0] ?? [];
        $images = $p['images'] ?? [];
        if (is_string($images)) {
            $images = json_decode($images, true) ?: [];
        }
        $image = collect([...(array) $images, ...($sku['Images'] ?? [])])->map(fn ($i) => trim((string) $i))->first(fn ($i) => $i !== '');

        return [
            'external_id' => (string) ($p['item_id'] ?? ''),
            'name' => (string) Arr::get($p, 'attributes.name', 'Tanpa nama'),
            'sku' => $sku['SellerSku'] ?? null,
            'price' => (int) round((float) ($sku['price'] ?? 0)),
            'special_price' => isset($sku['special_price']) && (float) $sku['special_price'] > 0 ? (int) round((float) $sku['special_price']) : null,
            'stock' => (int) collect($p['skus'] ?? [])->sum(fn ($s) => (int) ($s['quantity'] ?? 0)),
            'status' => $p['status'] ?? ($sku['Status'] ?? null),
            'category_id' => isset($p['primary_category']) ? (string) $p['primary_category'] : null,
            'image' => $image ? (str_starts_with($image, 'http') ? $image : 'https://'.ltrim($image, '/')) : null,
            'url' => $sku['Url'] ?? null,
            'external_updated_at' => isset($p['updated_time']) ? Carbon::createFromTimestampMs((int) $p['updated_time']) : null,
            'raw' => $p,
        ];
    }

    private function language(MarketplaceStore $store): string
    {
        return self::LANGUAGES[$store->country] ?? 'en_US';
    }

    private function expiresAt(mixed $seconds): ?Carbon
    {
        return is_numeric($seconds) && (int) $seconds > 0 ? now()->addSeconds((int) $seconds) : null;
    }
}

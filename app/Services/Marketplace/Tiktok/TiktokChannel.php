<?php

namespace App\Services\Marketplace\Tiktok;

use App\Models\MarketplaceApp;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Contracts\MarketplaceChannel;
use App\Services\Marketplace\Contracts\PublishesProducts;
use App\Services\Marketplace\MarketplaceException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Driver TikTok Shop (Partner Center, API 202309+):
 * otorisasi seller (service_id) -> auth_code -> token (access 7 hari), shop_cipher dari Get Authorized Shops,
 * produk (Search Products), kategori/rekomendasi/atribut, brand, gudang, upload gambar & Create Product.
 * Panduan: authorization-overview-202407, solution-guidance-product-api.
 */
class TiktokChannel implements MarketplaceChannel, PublishesProducts
{
    /** Mata uang harga SKU per negara toko. */
    public const CURRENCIES = ['id' => 'IDR', 'my' => 'MYR', 'th' => 'THB', 'vn' => 'VND', 'ph' => 'PHP', 'sg' => 'SGD', 'us' => 'USD', 'gb' => 'GBP'];

    /** Search Products memakai page_token; offset dipetakan ke token selama satu proses sinkron. */
    private array $pageTokens = [];

    public function __construct(private readonly TiktokClient $client) {}

    public function key(): string
    {
        return 'tiktok';
    }

    public function authorizeUrl(MarketplaceApp $app, string $state): string
    {
        if (! $app->service_id) {
            throw new MarketplaceException('Service ID app TikTok Shop belum diisi superadmin.');
        }

        $host = TiktokClient::AUTHORIZE_HOSTS[$app->region === 'us' ? 'us' : 'row'];

        // Redirect URL tidak dikirim di link: TikTok memakai Redirect URL yang diisi di Partner Center.
        return $host.'/open/authorize?'.http_build_query(['service_id' => $app->service_id, 'state' => $state]);
    }

    public function exchangeCode(MarketplaceApp $app, string $code, array $callback = []): array
    {
        $body = $this->client->auth($app, '/api/v2/token/get', ['auth_code' => $code, 'grant_type' => 'authorized_code']);
        $token = (array) ($body['data'] ?? []);

        if (empty($token['access_token'])) {
            throw new MarketplaceException('TikTok Shop tidak mengirim access token. Coba otorisasi ulang.');
        }

        // shop_cipher wajib untuk API toko; satu seller bisa punya beberapa toko (diambil yang pertama, sisanya dicatat).
        $shops = Arr::get($this->client->appGet($app, $token['access_token'], '/authorization/202309/shops'), 'data.shops', []);
        $shop = collect($shops)->firstWhere('region', strtoupper((string) ($token['seller_base_region'] ?? ''))) ?? ($shops[0] ?? null);

        if (! $shop) {
            throw new MarketplaceException('Akun TikTok Shop ini belum punya toko yang bisa diotorisasi.');
        }

        return [
            'external_id' => (string) $shop['id'],
            'name' => $shop['name'] ?? ($token['seller_name'] ?? null),
            'country' => strtolower((string) ($shop['region'] ?? $token['seller_base_region'] ?? '')) ?: null,
            'account' => $token['seller_name'] ?? null,
            'short_code' => $shop['code'] ?? null,
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? null,
            'access_expires_at' => $this->timestamp($token['access_token_expire_in'] ?? null),
            'refresh_expires_at' => $this->timestamp($token['refresh_token_expire_in'] ?? null),
            'meta' => [
                'shop_cipher' => $shop['cipher'] ?? null,
                'open_id' => $token['open_id'] ?? null,
                'seller_type' => $shop['seller_type'] ?? null,
                'granted_scopes' => $token['granted_scopes'] ?? [],
                'other_shops' => collect($shops)->reject(fn ($s) => $s['id'] === $shop['id'])->map(fn ($s) => Arr::only($s, ['id', 'name', 'region']))->values()->all(),
            ],
        ];
    }

    public function refreshToken(MarketplaceStore $store): void
    {
        if (! $store->canRefresh()) {
            throw new MarketplaceException('Masa otorisasi toko TikTok sudah habis. Seller perlu otorisasi ulang.');
        }

        try {
            $body = $this->client->auth($store->app, '/api/v2/token/refresh', ['refresh_token' => $store->refresh_token, 'grant_type' => 'refresh_token']);
        } catch (MarketplaceException $e) {
            $store->forceFill(['status' => MarketplaceStore::STATUS_EXPIRED, 'status_reason' => $e->getMessage()])->save();

            throw $e;
        }

        $token = (array) ($body['data'] ?? []);
        $store->forceFill([
            'access_token' => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? $store->refresh_token,
            'access_expires_at' => $this->timestamp($token['access_token_expire_in'] ?? null),
            'refresh_expires_at' => $this->timestamp($token['refresh_token_expire_in'] ?? null) ?? $store->refresh_expires_at,
            'status' => MarketplaceStore::STATUS_CONNECTED,
            'status_reason' => null,
        ])->save();
    }

    public function refreshMarginMinutes(): int
    {
        return 6 * 60;
    }

    public function scheduledRefreshMinutes(): int
    {
        return 2 * 24 * 60;
    }

    public function fetchProducts(MarketplaceStore $store, int $offset, int $limit): array
    {
        $tokenKey = $store->getKey().':'.$offset;
        if ($offset > 0 && ! isset($this->pageTokens[$tokenKey])) {
            return ['total' => $offset, 'items' => []];
        }

        $body = $this->client->shopPost($store, '/product/202502/products/search', (object) [], array_filter([
            'page_size' => min(100, $limit),
            'page_token' => $this->pageTokens[$tokenKey] ?? null,
        ]));

        $products = Arr::get($body, 'data.products', []);
        if ($next = Arr::get($body, 'data.next_page_token')) {
            $this->pageTokens[$store->getKey().':'.($offset + count($products))] = $next;
        }

        return [
            'total' => (int) Arr::get($body, 'data.total_count', count($products)),
            'items' => collect($products)->map(fn (array $p) => $this->normalizeProduct($p))->all(),
        ];
    }

    /** Kategori leaf yang boleh dipakai toko (permission AVAILABLE). Di-cache 12 jam per toko. */
    public function leafCategories(MarketplaceStore $store): array
    {
        return Cache::remember('marketplace:tiktok:categories:'.$store->getKey(), now()->addHours(12), function () use ($store) {
            $all = collect(Arr::get($this->client->shopGet($store, '/product/202309/categories'), 'data.categories', []))->keyBy('id');

            $path = function (array $c) use ($all) {
                $trail = [(string) $c['local_name']];
                $guard = 0;
                while (($parent = $all->get($c['parent_id'] ?? '0')) && $guard++ < 10) {
                    array_unshift($trail, (string) $parent['local_name']);
                    $c = $parent;
                }

                return implode(' › ', $trail);
            };

            return $all->filter(fn ($c) => ($c['is_leaf'] ?? false)
                    && (empty($c['permission_statuses']) || in_array('AVAILABLE', (array) $c['permission_statuses'], true)))
                ->map(fn ($c) => ['id' => (string) $c['id'], 'name' => (string) $c['local_name'], 'path' => $path($c)])
                ->values()->all();
        });
    }

    public function categorySuggestions(MarketplaceStore $store, string $productName, ?string $imageUrl = null): array
    {
        $body = $this->client->shopPost($store, '/product/202309/categories/recommend', ['product_title' => $productName]);
        $leafId = (string) Arr::get($body, 'data.leaf_category_id', '');
        $leaves = collect($this->leafCategories($store))->keyBy('id');

        if ($leafId === '') {
            return [];
        }

        $path = collect(Arr::get($body, 'data.categories', []))->pluck('name')->filter()->implode(' › ');

        return [$leaves->get($leafId) ?? ['id' => $leafId, 'name' => $leafId, 'path' => $path ?: 'Kategori #'.$leafId]];
    }

    /** Atribut produk kategori (PRODUCT_PROPERTY). Atribut penjualan/varian belum dipakai: produk katalog = 1 SKU. */
    public function categoryAttributes(MarketplaceStore $store, string $categoryId): array
    {
        return Cache::remember('marketplace:tiktok:attributes:'.$store->getKey().':'.$categoryId, now()->addHours(12), function () use ($store, $categoryId) {
            $attributes = Arr::get($this->client->shopGet($store, '/product/202309/categories/'.$categoryId.'/attributes'), 'data.attributes', []);

            return collect($attributes)
                ->filter(fn ($a) => ($a['type'] ?? 'PRODUCT_PROPERTY') === 'PRODUCT_PROPERTY')
                ->map(fn ($a) => [
                    'id' => (string) $a['id'],
                    'label' => (string) ($a['name'] ?? $a['id']),
                    // Nama field di API memang "is_requried".
                    'mandatory' => (bool) ($a['is_requried'] ?? $a['is_required'] ?? false),
                    'multiple' => (bool) ($a['is_multiple_selection'] ?? false),
                    'customizable' => (bool) ($a['is_customizable'] ?? false),
                    'options' => collect($a['values'] ?? [])->map(fn ($v) => ['id' => (string) $v['id'], 'name' => (string) $v['name']])->values()->all(),
                ])->sortByDesc('mandatory')->values()->all();
        });
    }

    /** Brand untuk kategori; tanpa brand = brand_id tidak dikirim. */
    public function brands(MarketplaceStore $store, string $categoryId, ?string $search = null): array
    {
        $body = $this->client->shopGet($store, '/product/202309/brands', array_filter([
            'category_id' => $categoryId,
            'page_size' => 100,
            'brand_name' => $search,
        ]));

        return ['items' => collect(Arr::get($body, 'data.brands', []))->map(fn ($b) => [
            'id' => (string) $b['id'],
            'name' => (string) $b['name'],
            'authorized' => ($b['authorized_status'] ?? 'AUTHORIZED') === 'AUTHORIZED',
        ])->values()->all()];
    }

    /** Gudang penjualan aktif toko (inventory SKU ditulis per warehouse_id). */
    public function warehouses(MarketplaceStore $store): array
    {
        $body = $this->client->shopGet($store, '/logistics/202309/warehouses');

        return collect(Arr::get($body, 'data.warehouses', []))
            ->filter(fn ($w) => ($w['type'] ?? 'SALES_WAREHOUSE') === 'SALES_WAREHOUSE' && ($w['effect_status'] ?? 'ENABLED') === 'ENABLED')
            ->map(fn ($w) => ['id' => (string) $w['id'], 'name' => (string) ($w['name'] ?? $w['id']), 'default' => (bool) ($w['is_default'] ?? false)])
            ->sortByDesc('default')->values()->all();
    }

    /** Upload gambar produk (multipart, field "data"), kembalikan uri untuk main_images. */
    public function uploadImage(MarketplaceStore $store, string $contents, string $filename): string
    {
        $body = $this->client->shopUpload($store, '/product/202309/images/upload', 'data', $contents, $filename, ['use_case' => 'MAIN_IMAGE']);
        $uri = (string) Arr::get($body, 'data.uri', '');

        if ($uri === '') {
            throw new MarketplaceException("Gambar {$filename} gagal diunggah ke TikTok Shop.");
        }

        return $uri;
    }

    /** @return array{product_id: string, raw: array} */
    public function createProduct(MarketplaceStore $store, array $payload): array
    {
        $body = $this->client->shopPost($store, '/product/202309/products', $payload);

        return ['product_id' => (string) Arr::get($body, 'data.product_id', ''), 'raw' => $body];
    }

    public function currency(MarketplaceStore $store): string
    {
        return self::CURRENCIES[$store->country] ?? 'USD';
    }

    private function normalizeProduct(array $p): array
    {
        $skus = $p['skus'] ?? [];
        $sku = $skus[0] ?? [];
        $price = Arr::get($sku, 'price.tax_exclusive_price') ?? Arr::get($sku, 'price.sale_price') ?? Arr::get($sku, 'price.amount') ?? 0;
        $stock = collect($skus)->sum(fn ($s) => collect($s['inventory'] ?? [])->sum('quantity'));

        return [
            'external_id' => (string) $p['id'],
            'name' => (string) ($p['title'] ?? 'Tanpa nama'),
            'sku' => ($sku['seller_sku'] ?? '') !== '' ? $sku['seller_sku'] : (count($skus) > 1 ? '(punya varian)' : null),
            'price' => (int) round((float) $price),
            'special_price' => null,
            'stock' => (int) $stock,
            'status' => $p['status'] ?? null,
            'category_id' => null,
            'image' => Arr::get($p, 'main_images.0.urls.0'),
            'url' => null,
            'external_updated_at' => isset($p['update_time']) ? Carbon::createFromTimestamp((int) $p['update_time']) : null,
            'raw' => $p,
        ];
    }

    /** Masa berlaku token TikTok berupa Unix timestamp (bukan detik dari sekarang). */
    private function timestamp(mixed $value): ?Carbon
    {
        return is_numeric($value) && (int) $value > 0 ? Carbon::createFromTimestamp((int) $value) : null;
    }
}

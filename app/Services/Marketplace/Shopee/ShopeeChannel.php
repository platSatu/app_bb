<?php

namespace App\Services\Marketplace\Shopee;

use App\Models\MarketplaceApp;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\Contracts\MarketplaceChannel;
use App\Services\Marketplace\Contracts\PublishesProducts;
use App\Services\Marketplace\MarketplaceException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Driver Shopee Open API v2: otorisasi toko, token (access 4 jam, refresh 30 hari & sekali pakai),
 * produk, kategori, atribut, brand, jasa kirim, upload gambar & add_item.
 */
class ShopeeChannel implements MarketplaceChannel, PublishesProducts
{
    // Bahasa nama kategori/atribut per negara toko.
    private const LANGUAGES = ['id' => 'id', 'my' => 'ms-my', 'th' => 'th', 'vn' => 'vi', 'ph' => 'en', 'sg' => 'en', 'tw' => 'zh-hant', 'br' => 'pt-br'];

    private const REFRESH_TOKEN_DAYS = 30;

    public function __construct(private readonly ShopeeClient $client) {}

    public function key(): string
    {
        return 'shopee';
    }

    public function authorizeUrl(MarketplaceApp $app, string $state): string
    {
        return $this->client->authHost($app).'/auth?'.http_build_query([
            'partner_id' => (int) $app->app_key,
            'auth_type' => 'seller',
            'redirect_uri' => $app->callbackUrl(),
            'response_type' => 'code',
            'state' => $state,
        ]);
    }

    /** Shopee mengirim shop_id bersama code di callback. */
    public function exchangeCode(MarketplaceApp $app, string $code, array $callback = []): array
    {
        $shopId = isset($callback['shop_id']) ? (string) $callback['shop_id'] : null;

        if (! $shopId || ! ctype_digit($shopId)) {
            throw new MarketplaceException('Shopee tidak mengirim shop_id. Otorisasi harus dilakukan dari akun toko (bukan main account).');
        }

        $body = $this->client->publicPost($app, '/api/v2/auth/token/get', [
            'code' => $code,
            'shop_id' => (int) $shopId,
            'partner_id' => (int) $app->app_key,
        ]);

        if (empty($body['access_token'])) {
            throw new MarketplaceException('Shopee tidak mengirim access token. Coba otorisasi ulang.');
        }

        $store = [
            'external_id' => $shopId,
            'name' => null,
            'country' => null,
            'account' => null,
            'short_code' => null,
            'access_token' => $body['access_token'],
            'refresh_token' => $body['refresh_token'] ?? null,
            'access_expires_at' => $this->expiresAt($body['expire_in'] ?? null),
            'refresh_expires_at' => now()->addDays(self::REFRESH_TOKEN_DAYS),
            'meta' => ['environment' => $app->environment],
        ];

        // Nama toko, negara & batas masa otorisasi (dipilih seller 7-365 hari).
        try {
            $info = $this->client->shopGet($this->tempStore($app, $shopId, $body['access_token']), '/api/v2/shop/get_shop_info');
            $shop = $info['response'] ?? $info;
            $store['name'] = $shop['shop_name'] ?? null;
            $store['country'] = isset($shop['region']) ? strtolower((string) $shop['region']) : null;
            $store['meta'] += [
                'auth_expire_time' => $shop['expire_time'] ?? null,
                'is_cb' => $shop['is_cb'] ?? null,
                'shop_status' => $shop['status'] ?? null,
            ];

            // Refresh token tidak berguna lewat masa otorisasi.
            if (! empty($shop['expire_time'])) {
                $authEnd = Carbon::createFromTimestamp((int) $shop['expire_time']);
                $store['refresh_expires_at'] = $authEnd->lt($store['refresh_expires_at']) ? $authEnd : $store['refresh_expires_at'];
            }
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
            $body = $this->client->publicPost($store->app, '/api/v2/auth/access_token/get', [
                'refresh_token' => $store->refresh_token,
                'partner_id' => (int) $store->app->app_key,
                'shop_id' => (int) $store->external_id,
            ]);
        } catch (MarketplaceException $e) {
            $store->forceFill(['status' => MarketplaceStore::STATUS_EXPIRED, 'status_reason' => $e->getMessage()])->save();

            throw $e;
        }

        $authEnd = data_get($store->meta, 'auth_expire_time');
        $refreshEnd = now()->addDays(self::REFRESH_TOKEN_DAYS);

        // Refresh token lama langsung hangus: yang baru WAJIB disimpan.
        $store->forceFill([
            'access_token' => $body['access_token'],
            'refresh_token' => $body['refresh_token'] ?? $store->refresh_token,
            'access_expires_at' => $this->expiresAt($body['expire_in'] ?? null),
            'refresh_expires_at' => $authEnd ? Carbon::createFromTimestamp((int) $authEnd)->min($refreshEnd) : $refreshEnd,
            'status' => MarketplaceStore::STATUS_CONNECTED,
            'status_reason' => null,
        ])->save();
    }

    public function refreshMarginMinutes(): int
    {
        return 30;
    }

    public function scheduledRefreshMinutes(): int
    {
        // Dijadwalkan tiap jam; token 4 jam diperpanjang saat sisa < 90 menit.
        return 90;
    }

    public function fetchProducts(MarketplaceStore $store, int $offset, int $limit): array
    {
        $list = $this->client->shopGet($store, '/api/v2/product/get_item_list', [
            'offset' => $offset,
            'page_size' => min(50, $limit),
            'item_status' => ['NORMAL', 'UNLIST', 'REVIEWING', 'BANNED'],
        ]);

        $ids = collect(Arr::get($list, 'response.item', []))->pluck('item_id')->filter()->all();
        $items = [];

        if ($ids) {
            $info = $this->client->shopGet($store, '/api/v2/product/get_item_base_info', ['item_id_list' => implode(',', $ids)]);
            $items = collect(Arr::get($info, 'response.item_list', []))->map(fn (array $p) => $this->normalizeProduct($p, $store))->all();
        }

        return ['total' => (int) Arr::get($list, 'response.total_count', 0), 'items' => $items];
    }

    /** Kategori paling akhir (has_children=false) beserta jalurnya. Di-cache 12 jam per app & toko. */
    public function leafCategories(MarketplaceStore $store): array
    {
        $key = 'marketplace:shopee:categories:'.$store->app_id.':'.$store->external_id;

        return Cache::remember($key, now()->addHours(12), function () use ($store) {
            $body = $this->client->shopGet($store, '/api/v2/product/get_category', ['language' => $this->language($store)]);
            $all = collect(Arr::get($body, 'response.category_list', []))->keyBy('category_id');

            $name = fn (array $c) => (string) ($c['display_category_name'] ?? $c['original_category_name'] ?? $c['category_id']);
            $path = function (array $c) use ($all, $name) {
                $trail = [$name($c)];
                $guard = 0;
                while (($parent = $all->get($c['parent_category_id'] ?? 0)) && $guard++ < 10) {
                    array_unshift($trail, $name($parent));
                    $c = $parent;
                }

                return implode(' › ', $trail);
            };

            return $all->reject(fn ($c) => $c['has_children'] ?? false)
                ->map(fn ($c) => ['id' => (string) $c['category_id'], 'name' => $name($c), 'path' => $path($c)])
                ->values()->all();
        });
    }

    public function categorySuggestions(MarketplaceStore $store, string $productName, ?string $imageUrl = null): array
    {
        $body = $this->client->shopGet($store, '/api/v2/product/category_recommend', ['item_name' => $productName]);
        $ids = array_map('strval', (array) Arr::get($body, 'response.category_id', []));
        $leaves = collect($this->leafCategories($store))->keyBy('id');

        return collect($ids)->map(fn ($id) => $leaves->get($id) ?? ['id' => $id, 'name' => '#'.$id, 'path' => 'Kategori #'.$id])->values()->all();
    }

    /**
     * Atribut kategori (get_attribute_tree), diratakan untuk form.
     * input_type: 1 pilih satu, 2 pilih satu/isi sendiri, 3 isi sendiri, 4 pilih banyak, 5 pilih banyak/isi sendiri.
     */
    public function categoryAttributes(MarketplaceStore $store, string $categoryId): array
    {
        $key = 'marketplace:shopee:attributes:'.$store->app_id.':'.$store->external_id.':'.$categoryId;

        return Cache::remember($key, now()->addHours(12), function () use ($store, $categoryId) {
            $body = $this->client->shopGet($store, '/api/v2/product/get_attribute_tree', [
                'category_id_list' => $categoryId,
                'language' => $this->language($store),
            ]);

            $tree = collect(Arr::get($body, 'response.list', []))->firstWhere('category_id', (int) $categoryId)['attribute_tree']
                ?? Arr::get($body, 'response.list.0.attribute_tree', []);

            return collect($tree)->map(fn (array $a) => [
                'id' => (int) $a['attribute_id'],
                'label' => (string) (collect($a['multi_lang'] ?? [])->first()['value'] ?? $a['name'] ?? $a['attribute_id']),
                'mandatory' => (bool) ($a['mandatory'] ?? false),
                'input_type' => (int) Arr::get($a, 'attribute_info.input_type', 3),
                'validation' => (int) Arr::get($a, 'attribute_info.input_validation_type', 0),
                'format_type' => (int) Arr::get($a, 'attribute_info.format_type', 1),
                'units' => array_values((array) Arr::get($a, 'attribute_info.attribute_unit_list', [])),
                'max' => (int) Arr::get($a, 'attribute_info.max_value_count', 0),
                'options' => collect($a['attribute_value_list'] ?? [])->map(fn ($v) => [
                    'id' => (int) $v['value_id'],
                    'name' => trim(($v['name'] ?? '').' '.($v['value_unit'] ?? '')),
                ])->values()->all(),
            ])->values()->all();
        });
    }

    /** Brand yang boleh dipakai kategori ini (status 1 = brand Shopee). No Brand = brand_id 0. */
    public function brands(MarketplaceStore $store, string $categoryId): array
    {
        $key = 'marketplace:shopee:brands:'.$store->app_id.':'.$store->external_id.':'.$categoryId;

        return Cache::remember($key, now()->addHours(12), function () use ($store, $categoryId) {
            $brands = [];
            $mandatory = false;
            $offset = 0;

            // Maks. 5 halaman x 100 brand, cukup untuk daftar pilihan; selebihnya ketik Brand ID manual.
            for ($page = 0; $page < 5; $page++) {
                $body = $this->client->shopGet($store, '/api/v2/product/get_brand_list', [
                    'category_id' => $categoryId, 'status' => 1, 'offset' => $offset, 'page_size' => 100, 'language' => $this->language($store),
                ]);
                $mandatory = (bool) Arr::get($body, 'response.is_mandatory', $mandatory);
                foreach (Arr::get($body, 'response.brand_list', []) as $b) {
                    $brands[] = ['id' => (int) $b['brand_id'], 'name' => (string) ($b['display_brand_name'] ?? $b['original_brand_name'])];
                }
                if (! Arr::get($body, 'response.has_next_page')) {
                    break;
                }
                $offset = (int) Arr::get($body, 'response.next_offset', $offset + 100);
            }

            return ['mandatory' => $mandatory, 'items' => $brands];
        });
    }

    /** Jasa kirim toko yang bisa dipasang ke produk: enabled=true & mask_channel_id=0. */
    public function logisticChannels(MarketplaceStore $store): array
    {
        $body = $this->client->shopGet($store, '/api/v2/logistics/get_channel_list');

        return collect(Arr::get($body, 'response.logistics_channel_list', []))
            ->filter(fn ($c) => ($c['enabled'] ?? false) && (int) ($c['mask_channel_id'] ?? 0) === 0)
            ->map(fn ($c) => [
                'id' => (int) $c['logistics_channel_id'],
                'name' => (string) $c['logistics_channel_name'],
                'fee_type' => (string) ($c['fee_type'] ?? ''),
                'sizes' => collect($c['size_list'] ?? [])->map(fn ($s) => ['id' => (string) ($s['size_id'] ?? ''), 'name' => (string) ($s['name'] ?? '')])->values()->all(),
                'max_weight' => $c['weight_limit']['item_max_weight'] ?? null,
            ])->values()->all();
    }

    /** Upload satu file gambar ke Media Space, kembalikan image_id. */
    public function uploadImage(MarketplaceStore $store, string $contents, string $filename): string
    {
        $body = $this->client->uploadImage($store->app, $contents, $filename);
        $id = (string) (Arr::get($body, 'response.image_info.image_id') ?: Arr::get($body, 'response.image_info_list.0.image_info.image_id', ''));

        if ($id === '') {
            $error = Arr::get($body, 'response.image_info_list.0.message') ?: 'tanpa keterangan';
            throw new MarketplaceException("Gambar {$filename} gagal diunggah ke Shopee ({$error}).");
        }

        return $id;
    }

    /** @return array{item_id: string, raw: array} */
    public function addItem(MarketplaceStore $store, array $payload): array
    {
        $body = $this->client->shopPost($store, '/api/v2/product/add_item', $payload);

        return ['item_id' => (string) Arr::get($body, 'response.item_id', ''), 'raw' => $body];
    }

    private function normalizeProduct(array $p, MarketplaceStore $store): array
    {
        $price = Arr::get($p, 'price_info.0', []);
        $original = (float) ($price['original_price'] ?? 0);
        $current = (float) ($price['current_price'] ?? $original);
        $stock = Arr::get($p, 'stock_info_v2.summary_info.total_available_stock')
            ?? collect(Arr::get($p, 'stock_info_v2.seller_stock', []))->sum('stock');

        return [
            'external_id' => (string) $p['item_id'],
            'name' => (string) ($p['item_name'] ?? 'Tanpa nama'),
            'sku' => ($p['item_sku'] ?? '') !== '' ? $p['item_sku'] : (($p['has_model'] ?? false) ? '(punya varian)' : null),
            'price' => (int) round($original),
            'special_price' => $current > 0 && $current < $original ? (int) round($current) : null,
            'stock' => (int) $stock,
            'status' => $p['item_status'] ?? null,
            'category_id' => isset($p['category_id']) ? (string) $p['category_id'] : null,
            'image' => Arr::get($p, 'image.image_url_list.0'),
            'url' => $this->productUrl($store, (string) $p['item_id']),
            'external_updated_at' => isset($p['update_time']) ? Carbon::createFromTimestamp((int) $p['update_time']) : null,
            'raw' => $p,
        ];
    }

    private function productUrl(MarketplaceStore $store, string $itemId): ?string
    {
        if ($store->app?->isSandbox()) {
            return null;
        }

        $domains = ['id' => 'shopee.co.id', 'my' => 'shopee.com.my', 'sg' => 'shopee.sg', 'th' => 'shopee.co.th', 'vn' => 'shopee.vn', 'ph' => 'shopee.ph', 'tw' => 'shopee.tw', 'br' => 'shopee.com.br'];
        $domain = $domains[$store->country] ?? null;

        return $domain ? "https://{$domain}/product/{$store->external_id}/{$itemId}" : null;
    }

    private function language(MarketplaceStore $store): string
    {
        return self::LANGUAGES[$store->country] ?? 'en';
    }

    private function expiresAt(mixed $seconds): ?Carbon
    {
        return is_numeric($seconds) && (int) $seconds > 0 ? now()->addSeconds((int) $seconds) : null;
    }

    /** Objek toko sementara untuk memanggil Shop API sebelum toko disimpan. */
    private function tempStore(MarketplaceApp $app, string $shopId, string $accessToken): MarketplaceStore
    {
        $store = new MarketplaceStore();
        $store->forceFill(['external_id' => $shopId, 'access_token' => $accessToken]);
        $store->setRelation('app', $app);

        return $store;
    }
}

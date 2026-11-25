<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Marketplace\Concerns\InMarketplaceArea;
use App\Models\CatalogProduct;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceStore;
use App\Models\PageCategory;
use App\Services\Marketplace\Contracts\PublishesProducts;
use App\Services\Marketplace\ListingPublisher;
use App\Services\Marketplace\MarketplaceException;
use App\Services\Marketplace\MarketplaceManager;
use App\Support\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Unggah produk Catalog Online ke toko marketplace.
 * Bagian yang sama di semua channel ada di sini: daftar produk + status upload, form, simpan,
 * dan endpoint JSON kategori/saran/atribut untuk form. Turunan per channel cukup mengisi:
 * channelKey(), rules(), publisher() dan (opsional) formData() untuk data tambahan form.
 * View form per channel: marketplace.{channel}.publish-form.
 */
abstract class StorePublishController extends Controller
{
    use InMarketplaceArea;

    public function __construct(protected readonly MarketplaceManager $manager) {}

    abstract protected function channelKey(): string;

    /** Aturan validasi isian form upload channel ini. */
    abstract protected function rules(): array;

    abstract protected function publisher(): ListingPublisher;

    /** Nama kolom untuk pesan validasi. */
    protected function attributeNames(): array
    {
        return [
            'category_id' => 'kategori',
            'brand_id' => 'brand',
            'seller_sku' => 'SKU',
            'quantity' => 'stok',
            'price' => 'harga',
            'special_price' => 'harga promo',
            'package_weight' => 'berat paket',
            'package_length' => 'panjang paket',
            'package_width' => 'lebar paket',
            'package_height' => 'tinggi paket',
        ];
    }

    /** Data tambahan untuk view form (mis. daftar jasa kirim Shopee). */
    protected function formData(MarketplaceStore $store, CatalogProduct $product): array
    {
        return [];
    }

    protected function driver(): PublishesProducts
    {
        return $this->manager->channel($this->channelKey());
    }

    public function index(Request $request, string $page, string $store): View
    {
        $model = $this->catalogPage($request, $page);
        $item = $this->findStore($model, $store, $this->channelKey());
        $search = trim((string) $request->query('search'));

        $products = CatalogProduct::where('page_id', $model->getKey())
            ->with(['images' => fn ($q) => $q->select('id', 'product_id', 'thumbnail', 'status', 'sort_order')])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('sort_order')
            ->paginate(20)
            ->withQueryString();

        return view('marketplace.shared.publish-index', [
            'page' => $model,
            'store' => $item,
            'products' => $products,
            'listings' => $this->latestListings($item, $products->pluck('id')->all()),
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function create(Request $request, string $page, string $store, string $product): View
    {
        $model = $this->catalogPage($request, $page, Menu::ABILITY_MANAGE);
        $item = $this->findStore($model, $store, $this->channelKey());
        $catalogProduct = $this->product($model, $product)->load('images');

        return view('marketplace.'.$this->channelKey().'.publish-form', [
            'page' => $model,
            'store' => $item,
            'product' => $catalogProduct,
            'lastListing' => $this->latestListings($item, [$catalogProduct->getKey()])->first(),
            'defaultSku' => Str::upper(Str::limit(Str::slug($catalogProduct->name), 40, '')).'-'.Str::upper(Str::random(4)),
            'routes' => $this->routes($request),
            ...$this->formData($item, $catalogProduct),
        ]);
    }

    public function store(Request $request, string $page, string $store, string $product): RedirectResponse
    {
        $model = $this->catalogPage($request, $page, Menu::ABILITY_MANAGE);
        $item = $this->findStore($model, $store, $this->channelKey());
        $catalogProduct = $this->product($model, $product);

        $data = $request->validate($this->rules(), [], $this->attributeNames());
        $listing = $this->publisher()->publishCatalogProduct($item, $catalogProduct, $data);

        if (! $listing->isSubmitted()) {
            return back()->withInput()->with('error', 'Produk belum terunggah ke '.$item->channelLabel().'. '.$listing->error);
        }

        return redirect()->route($this->routes($request).'.marketplace.'.$this->channelKey().'.publish.index', [$model, $item])
            ->with('success', "Produk {$catalogProduct->name} terkirim ke {$item->channelLabel()} (item ID {$listing->external_id}). Marketplace akan memeriksa produk sebelum tampil.");
    }

    /** Cari kategori leaf: ?q=kaos -> maks. 30 hasil. */
    public function categories(Request $request, string $page, string $store): JsonResponse
    {
        $item = $this->findStore($this->catalogPage($request, $page), $store, $this->channelKey());
        $q = Str::lower(trim((string) $request->query('q')));

        return $this->json($item, function () use ($item, $q) {
            $leaves = collect($this->driver()->leafCategories($item));

            return ['categories' => ($q === '' ? $leaves : $leaves->filter(fn ($c) => str_contains(Str::lower($c['path']), $q)))->take(30)->values()];
        });
    }

    /** Saran kategori dari nama produk. */
    public function suggest(Request $request, string $page, string $store): JsonResponse
    {
        $item = $this->findStore($this->catalogPage($request, $page), $store, $this->channelKey());
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'image' => ['nullable', 'url', 'max:500']]);

        return $this->json($item, fn () => ['categories' => $this->driver()->categorySuggestions($item, $data['name'], $data['image'] ?? null)]);
    }

    /** Atribut kategori untuk form dinamis. */
    public function attributes(Request $request, string $page, string $store, string $category): JsonResponse
    {
        $item = $this->findStore($this->catalogPage($request, $page), $store, $this->channelKey());
        abort_unless(ctype_digit($category), 404);

        return $this->json($item, fn () => ['attributes' => $this->filterAttributes($this->driver()->categoryAttributes($item, $category))]);
    }

    /** Turunan boleh menyaring atribut yang sudah punya kolom form sendiri. */
    protected function filterAttributes(array $attributes): array
    {
        return $attributes;
    }

    /** Jawaban JSON seragam; token dipastikan masih berlaku sebelum memanggil API. */
    protected function json(MarketplaceStore $store, callable $fn): JsonResponse
    {
        try {
            $this->manager->ensureFreshToken($store);

            return response()->json($fn());
        } catch (MarketplaceException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    protected function product(PageCategory $page, string $id): CatalogProduct
    {
        return CatalogProduct::where('page_id', $page->getKey())->findOrFail($id);
    }

    /** Unggahan terakhir per produk katalog ke toko ini, dikunci per source_id. */
    private function latestListings(MarketplaceStore $store, array $productIds)
    {
        return MarketplaceListing::where('store_id', $store->getKey())
            ->where('source_type', MarketplaceListing::SOURCE_CATALOG)
            ->whereIn('source_id', $productIds)
            ->latest()
            ->get()
            ->unique('source_id')
            ->keyBy('source_id');
    }
}

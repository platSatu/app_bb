<?php

namespace App\Http\Controllers\Marketplace\Lazada;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Marketplace\Concerns\InMarketplaceArea;
use App\Models\CatalogProduct;
use App\Models\MarketplaceListing;
use App\Models\MarketplaceStore;
use App\Models\PageCategory;
use App\Services\Marketplace\Lazada\LazadaChannel;
use App\Services\Marketplace\Lazada\LazadaPublisher;
use App\Services\Marketplace\MarketplaceException;
use App\Services\Marketplace\MarketplaceManager;
use App\Support\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Unggah produk Catalog Online ke toko Lazada: pilih kategori, brand, berat/dimensi, atribut wajib. */
class LazadaPublishController extends Controller
{
    use InMarketplaceArea;

    private const CHANNEL = 'lazada';

    public function __construct(
        private readonly LazadaChannel $lazada,
        private readonly MarketplaceManager $manager,
    ) {}

    public function index(Request $request, string $page, string $store): View
    {
        $model = $this->catalogPage($request, $page);
        $item = $this->findStore($model, $store, self::CHANNEL);
        $search = trim((string) $request->query('search'));

        $products = CatalogProduct::where('page_id', $model->getKey())
            ->with(['images' => fn ($q) => $q->select('id', 'product_id', 'thumbnail', 'status', 'sort_order')])
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('sort_order')
            ->paginate(20)
            ->withQueryString();

        // Unggahan terakhir per produk ke toko ini.
        $listings = MarketplaceListing::where('store_id', $item->getKey())
            ->where('source_type', MarketplaceListing::SOURCE_CATALOG)
            ->whereIn('source_id', $products->pluck('id'))
            ->latest()
            ->get()
            ->unique('source_id')
            ->keyBy('source_id');

        return view('marketplace.lazada.publish.index', [
            'page' => $model,
            'store' => $item,
            'products' => $products,
            'listings' => $listings,
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function create(Request $request, string $page, string $store, string $product): View
    {
        $model = $this->catalogPage($request, $page, Menu::ABILITY_MANAGE);
        $item = $this->findStore($model, $store, self::CHANNEL);
        $catalogProduct = $this->product($model, $product)->load('images');

        return view('marketplace.lazada.publish.form', [
            'page' => $model,
            'store' => $item,
            'product' => $catalogProduct,
            'lastListing' => MarketplaceListing::where('store_id', $item->getKey())
                ->where('source_type', MarketplaceListing::SOURCE_CATALOG)
                ->where('source_id', $catalogProduct->getKey())->latest()->first(),
            'defaultSku' => Str::upper(Str::limit(Str::slug($catalogProduct->name), 40, '')).'-'.Str::upper(Str::random(4)),
            'routes' => $this->routes($request),
        ]);
    }

    public function store(Request $request, string $page, string $store, string $product, LazadaPublisher $publisher): RedirectResponse
    {
        $model = $this->catalogPage($request, $page, Menu::ABILITY_MANAGE);
        $item = $this->findStore($model, $store, self::CHANNEL);
        $catalogProduct = $this->product($model, $product);

        $data = $request->validate([
            'category_id' => ['required', 'string', 'max:30', 'regex:/^\d+$/'],
            'brand_id' => ['required', 'string', 'max:30', 'regex:/^\d+$/'],
            'seller_sku' => ['required', 'string', 'max:100', 'not_regex:/[\^~<>|"]/'],
            'quantity' => ['required', 'integer', 'min:0', 'max:999999'],
            'price' => ['required', 'integer', 'min:1000'],
            'special_price' => ['nullable', 'integer', 'min:1000', 'lt:price'],
            'package_weight' => ['required', 'numeric', 'min:0.01', 'max:300'],
            'package_length' => ['required', 'numeric', 'min:1', 'max:300'],
            'package_width' => ['required', 'numeric', 'min:1', 'max:300'],
            'package_height' => ['required', 'numeric', 'min:1', 'max:300'],
            'package_content' => ['nullable', 'string', 'max:200'],
            'attributes' => ['nullable', 'array'],
            'attributes.*' => ['nullable'],
            'sku_attributes' => ['nullable', 'array'],
            'sku_attributes.*' => ['nullable'],
        ], [], [
            'category_id' => 'kategori Lazada',
            'brand_id' => 'brand',
            'seller_sku' => 'SKU',
            'quantity' => 'stok',
            'price' => 'harga',
            'special_price' => 'harga promo',
            'package_weight' => 'berat paket',
            'package_length' => 'panjang paket',
            'package_width' => 'lebar paket',
            'package_height' => 'tinggi paket',
        ]);

        $listing = $publisher->publishCatalogProduct($item, $catalogProduct, $data);

        if (! $listing->isSubmitted()) {
            return back()->withInput()->with('error', 'Produk belum terunggah ke Lazada. '.$listing->error);
        }

        return redirect()->route($this->routes($request).'.marketplace.lazada.publish.index', [$model, $item])
            ->with('success', "Produk {$catalogProduct->name} terkirim ke Lazada (item ID {$listing->external_id}). Lazada akan memeriksa (QC) sebelum produk tampil.");
    }

    /** Cari kategori leaf Lazada: ?q=kaos -> maks 30 hasil. */
    public function categories(Request $request, string $page, string $store): JsonResponse
    {
        $item = $this->findStore($this->catalogPage($request, $page), $store, self::CHANNEL);
        $q = Str::lower(trim((string) $request->query('q')));

        return $this->json(function () use ($item, $q) {
            $this->manager->ensureFreshToken($item);
            $leaves = collect($this->lazada->leafCategories($item));

            return ['categories' => ($q === '' ? $leaves : $leaves->filter(fn ($c) => str_contains(Str::lower($c['path']), $q)))->take(30)->values()];
        });
    }

    /** Saran kategori dari nama produk (+ foto pertama). */
    public function suggest(Request $request, string $page, string $store): JsonResponse
    {
        $item = $this->findStore($this->catalogPage($request, $page), $store, self::CHANNEL);
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'image' => ['nullable', 'url', 'max:500']]);

        return $this->json(function () use ($item, $data) {
            $this->manager->ensureFreshToken($item);

            return ['categories' => $this->lazada->categorySuggestions($item, $data['name'], $data['image'] ?? null)];
        });
    }

    /** Atribut kategori untuk form dinamis (yang sudah punya kolom sendiri disembunyikan). */
    public function attributes(Request $request, string $page, string $store, string $category): JsonResponse
    {
        $item = $this->findStore($this->catalogPage($request, $page), $store, self::CHANNEL);
        abort_unless(ctype_digit($category), 404);

        return $this->json(function () use ($item, $category) {
            $this->manager->ensureFreshToken($item);
            $hidden = [...LazadaPublisher::PRODUCT_FIELDS, ...LazadaPublisher::SKU_FIELDS];

            return ['attributes' => collect($this->lazada->categoryAttributes($item, $category))
                ->reject(fn ($a) => in_array($a['name'], $hidden, true))
                // Variasi (sale prop) belum didukung: produk katalog bizbos = 1 SKU.
                ->reject(fn ($a) => $a['is_sale_prop'] && ! $a['is_mandatory'])
                ->sortByDesc('is_mandatory')
                ->values()];
        });
    }

    private function json(callable $fn): JsonResponse
    {
        try {
            return response()->json($fn());
        } catch (MarketplaceException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function product(PageCategory $page, string $id): CatalogProduct
    {
        return CatalogProduct::where('page_id', $page->getKey())->findOrFail($id);
    }
}

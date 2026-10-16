<?php

namespace App\Http\Controllers\Page\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Http\Requests\Page\Catalog\CatalogProductRequest;
use App\Models\CatalogCategory;
use App\Models\CatalogProduct;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Services\Catalog\CatalogProductService;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogProductController extends Controller
{
    use ResolvesPage;

    public function __construct(private readonly CatalogProductService $products)
    {
    }

    public function index(Request $request, string $page): View
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_CATALOG);
        $search = trim((string) $request->query('search'));

        $products = CatalogProduct::where('page_id', $model->getKey())
            ->with(['category:id,name', 'images' => fn ($q) => $q->select('id', 'product_id', 'thumbnail', 'status', 'sort_order')])
            ->when($request->query('category'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('sort_order')
            ->paginate(20)
            ->withQueryString();

        return view('page.catalog.products.index', [
            'page' => $model,
            'products' => $products,
            'categories' => $this->categories($model),
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function create(Request $request, string $page): View
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_CATALOG, Menu::ABILITY_MANAGE);

        return $this->form($request, $model, new CatalogProduct(['status' => CatalogProduct::STATUS_ACTIVE]));
    }

    public function store(CatalogProductRequest $request, string $page): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_CATALOG, Menu::ABILITY_MANAGE);
        $product = $this->products->save($model, new CatalogProduct(), $request->productData(), $request->imageRows());

        return redirect()->route($this->routes($request).'.catalog.products.index', $model)->with('success', "Produk {$product->name} berhasil ditambahkan.");
    }

    public function edit(Request $request, string $page, string $product): View
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_CATALOG, Menu::ABILITY_MANAGE);

        return $this->form($request, $model, $this->product($model, $product)->load('images'));
    }

    public function update(CatalogProductRequest $request, string $page, string $product): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_CATALOG, Menu::ABILITY_MANAGE);
        $item = $this->products->save($model, $this->product($model, $product), $request->productData(), $request->imageRows());

        return redirect()->route($this->routes($request).'.catalog.products.index', $model)->with('success', "Produk {$item->name} berhasil diperbarui.");
    }

    public function destroy(Request $request, string $page, string $product): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_CATALOG, Menu::ABILITY_MANAGE);
        $item = $this->product($model, $product);
        $this->products->delete($item);

        return back()->with('success', "Produk {$item->name} dihapus. Pesanan lama tetap tersimpan.");
    }

    private function form(Request $request, PageCategory $page, CatalogProduct $product): View
    {
        return view('page.catalog.products.form', [
            'page' => $page,
            'product' => $product,
            'categories' => $this->categories($page),
            'routes' => $this->routes($request),
        ]);
    }

    private function product(PageCategory $page, string $id): CatalogProduct
    {
        return CatalogProduct::where('page_id', $page->getKey())->findOrFail($id);
    }

    private function categories(PageCategory $page)
    {
        return CatalogCategory::where('page_id', $page->getKey())->orderBy('sort_order')->get(['id', 'name', 'status']);
    }
}

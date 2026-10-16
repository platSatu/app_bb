<?php

namespace App\Http\Controllers\Page\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Models\CatalogCategory;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Support\Menu;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Kategori produk dalam satu katalog: baris add row + drag & drop, disimpan sekaligus. */
class CatalogCategoryController extends Controller
{
    use ResolvesPage;

    private const MAX = 50;

    public function index(Request $request, string $page): View
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_CATALOG);

        return view('page.catalog.categories', [
            'page' => $model,
            'categories' => CatalogCategory::where('page_id', $model->getKey())->withCount('products')->orderBy('sort_order')->get(),
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function sync(Request $request, string $page): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_CATALOG, Menu::ABILITY_MANAGE);

        $rows = $request->validate([
            'categories' => ['present', 'array', 'max:'.self::MAX],
            'categories.*.id' => ['nullable', 'uuid'],
            'categories.*.name' => ['required', 'string', 'max:100'],
            'categories.*.status' => ['required', Rule::in(array_keys(CatalogCategory::STATUS_LABELS))],
        ], ['categories.*.name.required' => 'Nama kategori wajib diisi di setiap baris.'])['categories'];

        DB::transaction(function () use ($model, $rows) {
            PageCategory::whereKey($model->getKey())->lockForUpdate()->first();
            $existing = CatalogCategory::where('page_id', $model->getKey())->get()->keyBy('id');
            $kept = [];

            foreach (array_values($rows) as $position => $row) {
                $category = $existing->get($row['id'] ?? '') ?? new CatalogCategory();
                $category->fill(['name' => $row['name'], 'status' => $row['status']]);
                $category->page_id = $model->getKey();
                $category->slug ??= Slug::unique(CatalogCategory::where('page_id', $model->getKey()), $row['name'], 'kategori', null, 120);
                $category->sort_order = $position;
                $category->save();
                $kept[] = $category->getKey();
            }

            // Produk di kategori yang dihapus tetap ada, hanya jadi tanpa kategori.
            CatalogCategory::where('page_id', $model->getKey())->whereKeyNot($kept)->delete();
        });

        return back()->with('success', 'Kategori produk berhasil disimpan.');
    }
}

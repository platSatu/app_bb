<?php

namespace App\Http\Controllers\Page\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Models\CatalogOrder;
use App\Models\PageType;
use App\Services\Catalog\CatalogOrderService;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CatalogOrderController extends Controller
{
    use ResolvesPage;

    public function index(Request $request, string $page): View
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_CATALOG);
        $search = trim((string) $request->query('search'));

        $orders = CatalogOrder::where('page_id', $model->getKey())
            ->withCount('items')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('page.catalog.orders.index', [
            'page' => $model,
            'orders' => $orders,
            'routes' => $this->routes($request),
        ]);
    }

    public function show(Request $request, string $page, string $order): View
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_CATALOG);

        return view('page.catalog.orders.show', [
            'page' => $model,
            'order' => CatalogOrder::where('page_id', $model->getKey())->with(['items', 'customer'])->findOrFail($order),
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function status(Request $request, CatalogOrderService $orders, string $page, string $order): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_CATALOG, Menu::ABILITY_MANAGE);
        $item = CatalogOrder::where('page_id', $model->getKey())->findOrFail($order);
        $status = $request->validate(['status' => ['required', Rule::in(array_keys(CatalogOrder::STATUS_LABELS))]])['status'];

        if (! $orders->changeStatus($item, $status)) {
            return back()->with('error', 'Status pesanan ini sudah final atau tidak berubah.');
        }

        return back()->with('success', 'Status pesanan diubah menjadi '.CatalogOrder::STATUS_LABELS[$status].($status === CatalogOrder::STATUS_CANCELLED ? '. Stok produk sudah dikembalikan.' : '.'));
    }
}

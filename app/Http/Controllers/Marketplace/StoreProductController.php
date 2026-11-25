<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Marketplace\Concerns\InMarketplaceArea;
use App\Models\MarketplaceProduct;
use App\Services\Marketplace\MarketplaceException;
use App\Services\Marketplace\MarketplaceManager;
use App\Services\Marketplace\ProductSyncService;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Produk yang ada di toko marketplace: tarik (sinkron) lalu tampilkan, siap dijual ulang ke channel lain.
 * Sama untuk semua channel; view bersama: marketplace.shared.products.
 */
abstract class StoreProductController extends Controller
{
    use InMarketplaceArea;

    abstract protected function channelKey(): string;

    public function index(Request $request, string $page, string $store, MarketplaceManager $manager): View
    {
        $model = $this->catalogPage($request, $page);
        $item = $this->findStore($model, $store, $this->channelKey());
        $search = trim((string) $request->query('search'));

        $products = MarketplaceProduct::where('store_id', $item->getKey())
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('marketplace.shared.products', [
            'page' => $model,
            'store' => $item,
            'products' => $products,
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
            // Tujuan jual ulang: channel lain (tombol aktif setelah fitur jual ulang dibuat).
            'resellChannels' => $manager->channels()->except($this->channelKey()),
        ]);
    }

    public function sync(Request $request, string $page, string $store, ProductSyncService $sync): RedirectResponse
    {
        $model = $this->catalogPage($request, $page, Menu::ABILITY_MANAGE);
        $item = $this->findStore($model, $store, $this->channelKey());

        try {
            $result = $sync->sync($item);
        } catch (MarketplaceException $e) {
            return back()->with('error', 'Sinkron produk gagal: '.$e->getMessage());
        }

        $note = $result['fetched'] < $result['total'] ? " (dari {$result['total']}; batas sekali sinkron diatur MARKETPLACE_SYNC_MAX_PRODUCTS)" : '';

        return back()->with('success', "{$result['fetched']} produk {$item->channelLabel()} berhasil ditarik{$note}.");
    }
}

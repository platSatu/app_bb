<?php

namespace App\Http\Controllers\Marketplace\Lazada;

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

/** Produk yang ada di toko Lazada: tarik (sinkron) lalu lihat, siap dijual ulang ke channel lain. */
class LazadaProductController extends Controller
{
    use InMarketplaceArea;

    private const CHANNEL = 'lazada';

    public function index(Request $request, string $page, string $store, MarketplaceManager $manager): View
    {
        $model = $this->catalogPage($request, $page);
        $item = $this->findStore($model, $store, self::CHANNEL);
        $search = trim((string) $request->query('search'));

        $products = MarketplaceProduct::where('store_id', $item->getKey())
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('marketplace.lazada.products', [
            'page' => $model,
            'store' => $item,
            'products' => $products,
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
            // Channel tujuan jual ulang (TikTok/Shopee) tampil "segera" sampai driver-nya aktif.
            'resellChannels' => $manager->channels()->except(self::CHANNEL),
        ]);
    }

    public function sync(Request $request, string $page, string $store, ProductSyncService $sync): RedirectResponse
    {
        $model = $this->catalogPage($request, $page, Menu::ABILITY_MANAGE);
        $item = $this->findStore($model, $store, self::CHANNEL);

        try {
            $result = $sync->sync($item);
        } catch (MarketplaceException $e) {
            return back()->with('error', 'Sinkron produk gagal: '.$e->getMessage());
        }

        $note = $result['fetched'] < $result['total'] ? " (dari {$result['total']}; batas sekali sinkron diatur MARKETPLACE_SYNC_MAX_PRODUCTS)" : '';

        return back()->with('success', "{$result['fetched']} produk Lazada berhasil ditarik{$note}.");
    }
}

<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Marketplace\Concerns\InMarketplaceArea;
use App\Models\MarketplaceApp;
use App\Models\MarketplaceStore;
use App\Services\Marketplace\MarketplaceManager;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Tab Marketplace di Catalog Online: daftar channel & toko yang sudah tersambung. */
class MarketplaceController extends Controller
{
    use InMarketplaceArea;

    public function index(Request $request, string $page, MarketplaceManager $manager): View
    {
        $model = $this->catalogPage($request, $page);

        $stores = MarketplaceStore::with('app:id,name')
            ->withCount(['products', 'listings'])
            ->where('page_id', $model->getKey())
            ->orderBy('created_at')
            ->get()
            ->groupBy('channel');

        return view('marketplace.index', [
            'page' => $model,
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
            // Hubungkan/sinkron/unggah hanya dari area owner (/pages); superadmin cukup melihat.
            'ownerArea' => $this->routes($request) === 'pages',
            'channels' => $manager->channels(),
            'stores' => $stores,
            'apps' => MarketplaceApp::active()->orderByDesc('is_default')->orderBy('name')->get(['id', 'channel', 'name', 'is_default'])->groupBy('channel'),
        ]);
    }
}

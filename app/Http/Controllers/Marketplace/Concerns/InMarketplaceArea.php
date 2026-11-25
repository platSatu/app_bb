<?php

namespace App\Http\Controllers\Marketplace\Concerns;

use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Models\MarketplaceStore;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Support\Menu;
use Illuminate\Http\Request;

/**
 * Marketplace tampil sebagai tab di page Catalog Online. Page dicari lewat ResolvesPage
 * (accessibleBy + area), toko selalu dicari di dalam page tersebut, jadi id dari URL tidak dipercaya begitu saja.
 */
trait InMarketplaceArea
{
    use ResolvesPage;

    protected function catalogPage(Request $request, string $id, string $ability = Menu::ABILITY_VIEW): PageCategory
    {
        return $this->templatePage($request, $id, PageType::TEMPLATE_CATALOG, $ability);
    }

    protected function findStore(PageCategory $page, string $id, string $channel): MarketplaceStore
    {
        return MarketplaceStore::with('app')
            ->where('page_id', $page->getKey())
            ->where('channel', $channel)
            ->findOrFail($id);
    }
}

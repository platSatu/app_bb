<?php

namespace App\Http\Controllers\Page\Concerns;

use App\Models\PageCategory;
use App\Support\Apps;
use App\Support\Menu;
use Illuminate\Http\Request;

/**
 * Sub-halaman page (form builder, data masuk) dipakai bersama oleh user & superadmin, di area Pages maupun Event.
 * Page selalu dicari lewat accessibleBy, jadi id dari URL tidak pernah dipercaya begitu saja.
 * Page juga harus milik area URL-nya: event hanya bisa dibuka lewat /events, page lain lewat /pages.
 */
trait ResolvesPage
{
    protected function page(Request $request, string $id, string $ability = Menu::ABILITY_VIEW): PageCategory
    {
        return PageCategory::accessibleBy($request->user(), $ability)
            ->forArea(Apps::pageArea($request->route()?->getName()))
            ->with('type')
            ->findOrFail($id);
    }

    /** Page dengan template tertentu; template lain = 404 supaya tab/URL tidak bisa dipaksa. */
    protected function templatePage(Request $request, string $id, string $template, string $ability = Menu::ABILITY_VIEW): PageCategory
    {
        $page = $this->page($request, $id, $ability);
        abort_unless($page->isTemplate($template), 404);

        return $page;
    }

    /** Prefix nama route: pages, events, superadmin.pages, atau superadmin.events. */
    protected function routes(Request $request): string
    {
        return Apps::pageRoutes($request->route()?->getName());
    }

    protected function canManage(Request $request): bool
    {
        $user = $request->user();

        return ! $user->isPlatformTeam()
            || $user->canAccess('superadmin.'.Apps::pageArea($request->route()?->getName()), Menu::ABILITY_MANAGE);
    }
}

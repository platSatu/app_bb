<?php

namespace App\Http\Controllers\Page\Concerns;

use App\Models\PageCategory;
use App\Support\Menu;
use Illuminate\Http\Request;

/**
 * Sub-halaman page (form builder, data masuk) dipakai bersama oleh user & superadmin.
 * Page selalu dicari lewat accessibleBy, jadi id dari URL tidak pernah dipercaya begitu saja.
 */
trait ResolvesPage
{
    protected function page(Request $request, string $id, string $ability = Menu::ABILITY_VIEW): PageCategory
    {
        return PageCategory::accessibleBy($request->user(), $ability)->with('type')->findOrFail($id);
    }

    /** Page dengan template tertentu; template lain = 404 supaya tab/URL tidak bisa dipaksa. */
    protected function templatePage(Request $request, string $id, string $template, string $ability = Menu::ABILITY_VIEW): PageCategory
    {
        $page = $this->page($request, $id, $ability);
        abort_unless($page->isTemplate($template), 404);

        return $page;
    }

    protected function routes(Request $request): string
    {
        return str_starts_with((string) $request->route()?->getName(), 'superadmin.') ? 'superadmin.pages' : 'pages';
    }

    protected function canManage(Request $request): bool
    {
        $user = $request->user();

        return ! $user->isPlatformTeam() || $user->canAccess('superadmin.pages', Menu::ABILITY_MANAGE);
    }
}

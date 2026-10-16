<?php

namespace App\Support;

use App\Models\PageCategory;
use App\Models\PageType;

/**
 * Menu atas yang tampil di halaman publik.
 * - Landing page: menunya sendiri.
 * - Page lain: menu landing "Beranda" branch-nya, kalau page ini ada di menu tersebut.
 */
class LandingNavigation
{
    public const FONTS = [
        'Inter' => 'Inter',
        'Poppins' => 'Poppins',
        'Plus Jakarta Sans' => 'Plus+Jakarta+Sans',
        'Lora' => 'Lora',
    ];

    private static array $cache = [];

    /** @return array{home: PageCategory, items: array<int, array{label: string, url: string, active: bool}>, color: string, font: string, logo: ?string}|null */
    public static function for(PageCategory $page): ?array
    {
        return self::$cache[$page->getKey()] ??= self::resolve($page);
    }

    private static function resolve(PageCategory $page): ?array
    {
        if ($page->isTemplate(PageType::TEMPLATE_LANDING)) {
            return self::build($page, $page);
        }

        $home = PageCategory::query()
            ->where('branch_id', $page->branch_id)
            ->where('status', PageCategory::STATUS_ACTIVE)
            ->where('settings->landing->is_home', true)
            ->whereHas('type', fn ($q) => $q->where('template', PageType::TEMPLATE_LANDING))
            ->first();

        $inMenu = $home && collect($home->setting('landing.menu', []))->contains('page_id', $page->getKey());

        return $inMenu ? self::build($home, $page) : null;
    }

    private static function build(PageCategory $home, PageCategory $current): array
    {
        $menu = collect($home->setting('landing.menu', []));
        $pages = PageCategory::where('branch_id', $home->branch_id)->where('status', PageCategory::STATUS_ACTIVE)
            ->whereKey($menu->pluck('page_id')->filter())->get(['id', 'slug'])->keyBy('id');

        $items = $menu->map(function ($item) use ($pages, $current) {
            $target = $pages->get($item['page_id'] ?? '');
            $url = $target ? route('page.show', $target->slug) : ($item['url'] ?? null);

            return $url ? ['label' => $item['label'], 'url' => $url, 'active' => $target?->is($current) ?? false] : null;
        })->filter()->values()->all();

        $logo = $home->setting('landing.logo');

        return [
            'home' => $home,
            'items' => $items,
            'color' => $home->setting('landing.color') ?: '#4f46e5',
            'font' => array_key_exists($home->setting('landing.font'), self::FONTS) ? $home->setting('landing.font') : 'Inter',
            'logo' => $logo ? \Illuminate\Support\Facades\Storage::disk('public')->url($logo) : $home->branch?->company?->logoUrl(),
        ];
    }
}

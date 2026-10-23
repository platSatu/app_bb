<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

class Menu
{
    public const ABILITY_VIEW = 'view';
    public const ABILITY_MANAGE = 'manage';

    /** Semua submenu (permission_key => item), lintas section & group. */
    public static function items(): Collection
    {
        return collect(config('menu'))->flatMap(fn (array $section) => static::sectionItems($section));
    }

    public static function find(string $key): ?array
    {
        return static::items()->get($key);
    }

    /** Submenu yang boleh diberikan ke role, item berisi 'group' (nama menu induk). */
    public static function grantable(string $section): Collection
    {
        return static::sectionItems(config("menu.{$section}", []))
            ->reject(fn (array $item) => $item['superadmin_only'] ?? false);
    }

    /**
     * Section > group > (submenu >) item yang boleh dilihat user; yang kosong tidak ditampilkan.
     * Menu aplikasi milik user (owner_app) tidak ditampilkan untuk tim platform,
     * karena mereka mengelolanya lewat menu Applications di area superadmin.
     */
    public static function visibleSections(User $user): Collection
    {
        $visible = fn (array $items) => collect($items)->filter(
            fn (array $item, string $key) => ($item['sidebar'] ?? true) && $user->canAccess($key)
        );

        return collect(config('menu'))
            ->reject(fn (array $section) => ($section['owner_app'] ?? false) && $user->isPlatformTeam())
            ->map(fn (array $section) => [
                'label' => $section['label'],
                'groups' => collect($section['groups'])
                    ->map(fn (array $group) => [
                        ...$group,
                        'items' => $visible($group['items'] ?? []),
                        'submenus' => collect($group['submenus'] ?? [])
                            ->map(fn (array $submenu) => [...$submenu, 'items' => $visible($submenu['items'])])
                            ->filter(fn (array $submenu) => $submenu['items']->isNotEmpty()),
                    ])
                    ->filter(fn (array $group) => $group['items']->isNotEmpty() || $group['submenus']->isNotEmpty()),
            ])
            ->filter(fn (array $section) => $section['groups']->isNotEmpty());
    }

    /** Pola 'active' menu cocok dengan route sekarang. Pola berawalan '!' mengecualikan (mis. '!pages.customers.*'). */
    public static function isActive(string|array $patterns): bool
    {
        [$exclude, $include] = collect((array) $patterns)->partition(fn (string $p) => str_starts_with($p, '!'));

        return request()->routeIs($include->all())
            && ! ($exclude->isNotEmpty() && request()->routeIs($exclude->map(fn ($p) => substr($p, 1))->all()));
    }

    /** Item yang dipakai sebagai tab: hanya yang boleh dibuka user. */
    public static function tabs(User $user, array $keys): Collection
    {
        return collect($keys)
            ->mapWithKeys(fn (string $key) => [$key => static::find($key)])
            ->filter(fn (?array $item, string $key) => $item && $user->canAccess($key));
    }

    private static function sectionItems(array $section): Collection
    {
        $extra = fn (string $group, string $icon, ?string $app) => fn (array $item) => [
            ...$item,
            'group' => $group,
            'icon' => $icon,
            'owner_app' => $section['owner_app'] ?? false,
            'app' => $app,
        ];

        return collect($section['groups'] ?? [])->flatMap(fn (array $group) => collect($group['items'] ?? [])
            ->map($extra($group['label'], $group['icon'], $group['app'] ?? null))
            ->merge(collect($group['submenus'] ?? [])->flatMap(
                fn (array $submenu) => collect($submenu['items'])->map($extra($group['label'].' › '.$submenu['label'], $group['icon'], $group['app'] ?? null))
            )));
    }
}

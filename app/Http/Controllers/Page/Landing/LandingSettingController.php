<?php

namespace App\Http\Controllers\Page\Landing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Services\Media\ImageUploader;
use App\Support\LandingNavigation;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Menu & Tampilan: warna, font, logo, Beranda branch, dan menu ke page lain satu branch. */
class LandingSettingController extends Controller
{
    use ResolvesPage;

    private const MAX_MENU = 10;

    public function edit(Request $request, string $page): View
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_LANDING);

        return view('page.landing.settings', [
            'page' => $model,
            'branchPages' => $this->branchPages($model),
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function update(Request $request, ImageUploader $images, string $page): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_LANDING, Menu::ABILITY_MANAGE);
        $branchPageIds = $this->branchPages($model)->pluck('id')->all();

        $data = $request->validate([
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'font' => ['required', Rule::in(array_keys(LandingNavigation::FONTS))],
            'is_home' => ['boolean'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_logo' => ['boolean'],
            'menu' => ['nullable', 'array', 'max:'.self::MAX_MENU],
            'menu.*.label' => ['required', 'string', 'max:40'],
            'menu.*.page_id' => ['nullable', Rule::in($branchPageIds)],
            'menu.*.url' => ['nullable', 'url', 'max:500'],
        ], [
            'menu.*.label.required' => 'Teks menu wajib diisi.',
            'menu.*.page_id.in' => 'Page tujuan harus milik branch yang sama.',
            'menu.*.url.url' => 'Link luar harus diawali https://',
        ]);

        $menu = collect($data['menu'] ?? [])->values()->map(fn ($item) => [
            'label' => trim($item['label']),
            'page_id' => $item['page_id'] ?? null,
            'url' => filled($item['page_id'] ?? null) ? null : ($item['url'] ?? null),
        ]);

        if ($menu->contains(fn ($item) => ! $item['page_id'] && ! $item['url'])) {
            throw ValidationException::withMessages(['menu' => 'Setiap menu perlu tujuan: pilih page atau isi link luar.']);
        }

        $oldLogo = $model->setting('landing.logo');
        $logo = $request->file('logo')
            ? $images->store($request->file('logo'), 'landing/'.$model->getKey(), 'logo', 400)
            : ($request->boolean('remove_logo') ? null : $oldLogo);

        DB::transaction(function () use ($model, $data, $menu, $logo, $request) {
            // Satu branch hanya punya satu Beranda.
            if ($request->boolean('is_home')) {
                PageCategory::where('branch_id', $model->branch_id)->whereKeyNot($model->getKey())
                    ->where('settings->landing->is_home', true)->lockForUpdate()->get()
                    ->each(fn ($other) => $other->forceFill(['settings' => array_replace_recursive($other->settings ?? [], ['landing' => ['is_home' => false]])])->save());
            }

            $settings = $model->settings ?? [];
            $settings['landing'] = [
                'color' => $data['color'],
                'font' => $data['font'],
                'is_home' => $request->boolean('is_home'),
                'logo' => $logo,
                'menu' => $menu->all(),
            ];

            $model->settings = $settings;
            $model->save();
        });

        if ($oldLogo && $oldLogo !== $logo) {
            $images->delete($oldLogo);
        }

        return back()->with('success', 'Menu & tampilan berhasil disimpan.');
    }

    private function branchPages(PageCategory $page)
    {
        return PageCategory::where('branch_id', $page->branch_id)->whereKeyNot($page->getKey())->with('type:id,name')->orderBy('name')->get(['id', 'name', 'page_type_id', 'status']);
    }
}

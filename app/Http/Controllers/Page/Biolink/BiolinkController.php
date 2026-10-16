<?php

namespace App\Http\Controllers\Page\Biolink;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Bio Link: daftar tombol link (add row + geser). Disimpan di settings.biolink, tanpa tabel baru. */
class BiolinkController extends Controller
{
    use ResolvesPage;

    private const MAX_LINKS = 30;

    public function edit(Request $request, string $page): View
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_BIOLINK);

        return view('page.biolink.links', [
            'page' => $model,
            'branchPages' => $this->branchPages($model),
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function update(Request $request, string $page): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_BIOLINK, Menu::ABILITY_MANAGE);

        $data = $request->validate([
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'links' => ['nullable', 'array', 'max:'.self::MAX_LINKS],
            'links.*.label' => ['required', 'string', 'max:60'],
            'links.*.page_id' => ['nullable', Rule::in($this->branchPages($model)->pluck('id')->all())],
            'links.*.url' => ['nullable', 'url:http,https', 'max:500'],
            'links.*.active' => ['boolean'],
        ], [
            'links.*.label.required' => 'Teks tombol wajib diisi.',
            'links.*.url.url' => 'Link harus diawali https://',
            'links.*.page_id.in' => 'Page tujuan harus milik branch yang sama.',
            'links.max' => 'Maksimal '.self::MAX_LINKS.' link.',
        ]);

        $links = collect($data['links'] ?? [])->values()->map(fn ($link) => [
            'label' => trim($link['label']),
            'page_id' => $link['page_id'] ?? null,
            'url' => filled($link['page_id'] ?? null) ? null : ($link['url'] ?? null),
            'active' => (bool) ($link['active'] ?? false),
        ]);

        if ($links->contains(fn ($link) => ! $link['page_id'] && ! $link['url'])) {
            throw ValidationException::withMessages(['links' => 'Setiap link perlu tujuan: pilih page atau isi link luar.']);
        }

        $model->settings = [...($model->settings ?? []), 'biolink' => ['color' => $data['color'], 'links' => $links->all()]];
        $model->save();

        return back()->with('success', 'Bio link berhasil disimpan.');
    }

    private function branchPages(PageCategory $page)
    {
        return PageCategory::where('branch_id', $page->branch_id)->whereKeyNot($page->getKey())->with('type:id,name')->orderBy('name')->get(['id', 'name', 'page_type_id', 'status']);
    }
}

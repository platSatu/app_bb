<?php

namespace App\Http\Controllers\Superadmin\Page;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\Page\PageTypeRequest;
use App\Models\PageType;
use App\Services\Media\ImageUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageTypeController extends Controller
{
    public function __construct(private readonly ImageUploader $images)
    {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $types = PageType::query()
            ->withCount('pages')
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('superadmin.page-type.index', compact('types'));
    }

    public function create(): View
    {
        return view('superadmin.page-type.form', ['type' => new PageType(['status' => PageType::STATUS_ACTIVE, 'template' => PageType::TEMPLATE_REGISTRATION])]);
    }

    public function store(PageTypeRequest $request): RedirectResponse
    {
        return $this->save(new PageType(), $request, 'ditambahkan');
    }

    public function edit(PageType $pageType): View
    {
        return view('superadmin.page-type.form', ['type' => $pageType]);
    }

    public function update(PageTypeRequest $request, PageType $pageType): RedirectResponse
    {
        return $this->save($pageType, $request, 'diperbarui');
    }

    public function destroy(PageType $pageType): RedirectResponse
    {
        if ($pageType->pages()->exists()) {
            return back()->with('error', "Kategori {$pageType->name} masih dipakai page user. Nonaktifkan saja supaya tidak bisa dipilih lagi.");
        }

        $image = $pageType->image;
        $pageType->delete();
        $this->images->delete($image);

        return redirect()->route('superadmin.page-types.index')->with('success', "Kategori {$pageType->name} sudah dihapus.");
    }

    private function save(PageType $type, PageTypeRequest $request, string $verb): RedirectResponse
    {
        $type->fill($request->safe()->except('image'));
        $this->images->saveWith($type, 'image', $request->file('image'), 'page-types', 600);

        return redirect()->route('superadmin.page-types.index')->with('success', "Kategori {$type->name} berhasil {$verb}.");
    }
}

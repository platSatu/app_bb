<?php

namespace App\Http\Controllers\Superadmin\CategoryApplication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\CategoryApplication\CategoryApplicationRequest;
use App\Models\CategoryApplication;
use App\Services\Media\ImageUploader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryApplicationController extends Controller
{
    public function __construct(private readonly ImageUploader $images)
    {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $categories = CategoryApplication::query()
            ->withCount('packages')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('superadmin.category-application.index', compact('categories'));
    }

    public function create(): View
    {
        return view('superadmin.category-application.create', ['category' => new CategoryApplication(['status' => CategoryApplication::STATUS_ACTIVE])]);
    }

    public function store(CategoryApplicationRequest $request): RedirectResponse
    {
        return $this->save(new CategoryApplication(), $request, 'ditambahkan');
    }

    public function edit(CategoryApplication $categoryApplication): View
    {
        return view('superadmin.category-application.edit', ['category' => $categoryApplication]);
    }

    public function update(CategoryApplicationRequest $request, CategoryApplication $categoryApplication): RedirectResponse
    {
        return $this->save($categoryApplication, $request, 'diperbarui');
    }

    public function destroy(CategoryApplication $categoryApplication): RedirectResponse
    {
        if ($categoryApplication->packages()->exists()) {
            return back()->with('error', "Kategori {$categoryApplication->name} masih dipakai package. Nonaktifkan saja, atau pindahkan package-nya dulu.");
        }

        $image = $categoryApplication->image;
        $categoryApplication->delete();
        $this->images->delete($image);

        return redirect()->route('superadmin.category-applications.index')
            ->with('success', "Kategori {$categoryApplication->name} sudah dihapus.");
    }

    private function save(CategoryApplication $category, CategoryApplicationRequest $request, string $verb): RedirectResponse
    {
        $category->fill($request->safe()->except('image'));
        $this->images->saveWith($category, 'image', $request->file('image'), 'category-applications', 600);

        return redirect()->route('superadmin.category-applications.index')
            ->with('success', "Kategori {$category->name} berhasil {$verb}.");
    }
}

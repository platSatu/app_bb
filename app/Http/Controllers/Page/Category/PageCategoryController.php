<?php

namespace App\Http\Controllers\Page\Category;

use App\Http\Controllers\Controller;
use App\Http\Requests\Page\Category\PageCategoryRequest;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Services\Page\PageCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Page selalu milik branch perusahaan user yang login; id dari URL tidak dipercaya begitu saja. */
class PageCategoryController extends Controller
{
    private const ROUTES = 'pages';

    public function __construct(private readonly PageCategoryService $pages)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        if (! $branches = $this->branches($request)) {
            return $this->branchRequired();
        }

        $search = trim((string) $request->query('search'));

        $pages = PageCategory::ownedBy($request->user())
            ->with(['type:id,name,template', 'branch:id,name'])
            ->withCount(['submissions', 'catalogOrders', 'quizAttempts', 'ticketOrders'])
            ->when($request->query('type'), fn ($q, $id) => $q->where('page_type_id', $id))
            ->when($request->query('branch'), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('slug', 'like', "%{$search}%")))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('page.category.index', [
            'pages' => $pages,
            'branches' => $branches,
            'types' => PageType::active()->orderBy('name')->get(['id', 'name']),
            'routes' => self::ROUTES,
            'canManage' => true,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $branches = $this->branches($request)) {
            return $this->branchRequired();
        }

        return $this->form(new PageCategory(['status' => PageCategory::STATUS_ACTIVE]), $branches);
    }

    public function store(PageCategoryRequest $request): RedirectResponse
    {
        $page = $this->pages->save(new PageCategory(), $request->branch(), $request->validated());

        return redirect()->route('pages.index')
            ->with('success', "Page {$page->name} berhasil dibuat. Link dan QR code-nya sudah siap, klik Setting untuk mengatur isinya.");
    }

    public function edit(Request $request, string $page): View
    {
        return $this->form($this->owned($request, $page), $this->branches($request));
    }

    public function update(PageCategoryRequest $request, string $page): RedirectResponse
    {
        $model = $this->pages->save($this->owned($request, $page), $request->branch(), $request->validated());

        return redirect()->route('pages.index')->with('success', "Page {$model->name} berhasil diperbarui.");
    }

    public function destroy(Request $request, string $page): RedirectResponse
    {
        $model = $this->owned($request, $page);

        try {
            $this->pages->delete($model);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('pages.index')->with('success', "Page {$model->name} sudah dihapus.");
    }

    public function qrcode(Request $request, string $page): StreamedResponse
    {
        $model = $this->owned($request, $page);
        abort_unless($model->qrcodePath() && Storage::disk('public')->exists($model->qrcodePath()), 404);

        return Storage::disk('public')->download($model->qrcodePath(), 'qrcode-'.$model->slug.'.png');
    }

    private function form(PageCategory $page, $branches): View
    {
        return view($page->exists ? 'page.category.edit' : 'page.category.create', [
            'page' => $page,
            'branches' => $branches,
            // Hanya kategori aktif, plus kategori lama page ini kalau sudah dinonaktifkan.
            'types' => PageType::query()
                ->where(fn ($q) => $q->active()->when($page->page_type_id, fn ($w) => $w->orWhere('id', $page->page_type_id)))
                ->orderBy('name')
                ->get(['id', 'name', 'status']),
            'routes' => self::ROUTES,
        ]);
    }

    private function owned(Request $request, string $id): PageCategory
    {
        return PageCategory::accessibleBy($request->user())->findOrFail($id);
    }

    private function branches(Request $request)
    {
        $branches = $request->user()->company?->branches()->whereIn('id', \App\Services\Subscription\AppAccess::activeBranches('pages'))->orderBy('name')->get(['id', 'name', 'status']);

        return $branches?->isNotEmpty() ? $branches : null;
    }

    private function branchRequired(): RedirectResponse
    {
        return redirect()->route('settings.branches.index')
            ->with('info', 'Buat branch dulu ya, setiap page akan tercatat di salah satu branch Anda.');
    }
}

<?php

namespace App\Http\Controllers\Page\Category;

use App\Http\Controllers\Controller;
use App\Http\Requests\Page\Category\PageCategoryRequest;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Services\Page\PageCategoryService;
use App\Services\Subscription\AppAccess;
use App\Support\Apps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Page selalu milik branch perusahaan user yang login; id dari URL tidak dipercaya begitu saja.
 * Dipakai dua menu: Pages (/pages) dan Event (/events). Event = page bertemplate tiket, dengan paket sendiri.
 */
class PageCategoryController extends Controller
{
    public function __construct(private readonly PageCategoryService $pages)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        if (! $branches = $this->branches($request)) {
            return $this->branchRequired();
        }

        $search = trim((string) $request->query('search'));

        $area = $this->area($request);

        $pages = PageCategory::ownedBy($request->user())
            ->forArea($area)
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
            'types' => $this->typeQuery($area)->active()->orderBy('name')->get(['id', 'name']),
            'routes' => $area,
            'canManage' => true,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $branches = $this->branches($request)) {
            return $this->branchRequired();
        }

        // Event baru selalu lewat simulasi pendapatan & pilihan jenis event dulu.
        if ($this->area($request) === Apps::EVENTS && ! $request->filled('fee_payer')) {
            return redirect()->route('events.ticket-fee', array_filter(['type' => $request->query('type')]));
        }

        return $this->form($request, new PageCategory(['status' => PageCategory::STATUS_ACTIVE]), $branches);
    }

    public function store(PageCategoryRequest $request): RedirectResponse
    {
        $page = $this->pages->save(new PageCategory(), $request->branch(), $request->validated());
        $routes = $this->area($request);

        // Event (page tiket): langkah berikutnya selalu mengisi kategori tiket.
        if ($page->isTemplate(PageType::TEMPLATE_TICKET)) {
            return redirect()->route($routes.'.ticket.categories.index', $page)
                ->with('success', "Event {$page->name} berhasil dibuat. Sekarang isi kategori tiketnya, mis. Reguler atau VIP.");
        }

        return redirect()->route($routes.'.index')
            ->with('success', "Page {$page->name} berhasil dibuat. Link dan QR code-nya sudah siap, klik Setting untuk mengatur isinya.");
    }

    public function edit(Request $request, string $page): View
    {
        return $this->form($request, $this->owned($request, $page), $this->branches($request));
    }

    public function update(PageCategoryRequest $request, string $page): RedirectResponse
    {
        $model = $this->pages->save($this->owned($request, $page), $request->branch(), $request->validated());

        return redirect()->route($this->area($request).'.index')->with('success', $this->noun($request)." {$model->name} berhasil diperbarui.");
    }

    public function destroy(Request $request, string $page): RedirectResponse
    {
        $model = $this->owned($request, $page);

        try {
            $this->pages->delete($model);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route($this->area($request).'.index')->with('success', $this->noun($request)." {$model->name} sudah dihapus.");
    }

    public function qrcode(Request $request, string $page): StreamedResponse
    {
        $model = $this->owned($request, $page);
        abort_unless($model->qrcodePath() && Storage::disk('public')->exists($model->qrcodePath()), 404);

        return Storage::disk('public')->download($model->qrcodePath(), 'qrcode-'.$model->slug.'.png');
    }

    private function form(Request $request, PageCategory $page, $branches): View
    {
        $area = $this->area($request);

        return view($page->exists ? 'page.category.edit' : 'page.category.create', [
            'page' => $page,
            'branches' => $branches,
            // Hanya kategori aktif milik area ini, plus kategori lama page ini kalau sudah dinonaktifkan.
            'types' => $this->typeQuery($area)
                ->where(fn ($q) => $q->active()->when($page->page_type_id, fn ($w) => $w->orWhere('id', $page->page_type_id)))
                ->orderBy('name')
                ->get(['id', 'name', 'status', 'template']),
            'routes' => $area,
        ]);
    }

    private function owned(Request $request, string $id): PageCategory
    {
        return PageCategory::accessibleBy($request->user())->forArea($this->area($request))->findOrFail($id);
    }

    /** Branch yang paket layanan area ini (Pages atau Event) masih aktif. */
    private function branches(Request $request)
    {
        $branches = $request->user()->company?->branches()->whereIn('id', AppAccess::activeBranches($this->area($request)))->orderBy('name')->get(['id', 'name', 'status']);

        return $branches?->isNotEmpty() ? $branches : null;
    }

    private function branchRequired(): RedirectResponse
    {
        return redirect()->route('settings.branches.index')
            ->with('info', 'Buat branch dulu ya, setiap page dan event akan tercatat di salah satu branch Anda.');
    }

    /** 'events' atau 'pages', dibaca dari nama route. Sekaligus prefix route area ini. */
    private function area(Request $request): string
    {
        return Apps::pageArea($request->route()?->getName());
    }

    private function noun(Request $request): string
    {
        return $this->area($request) === Apps::EVENTS ? 'Event' : 'Page';
    }

    /** Kategori page milik area: Event = kategori bertemplate tiket, Pages = sisanya. */
    private function typeQuery(string $area)
    {
        return PageType::query()->when(
            $area === Apps::EVENTS,
            fn ($q) => $q->whereIn('template', Apps::EVENT_TEMPLATES),
            fn ($q) => $q->whereNotIn('template', Apps::EVENT_TEMPLATES),
        );
    }
}

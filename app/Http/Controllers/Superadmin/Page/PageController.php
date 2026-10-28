<?php

namespace App\Http\Controllers\Superadmin\Page;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\Page\PageRequest;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\UserBranch;
use App\Services\Page\PageCategoryService;
use App\Support\Apps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Semua page milik semua user, dengan akses penuh. Form & tampilan sama dengan milik user.
 * Dipakai dua menu: Applications › Pages (/superadmin/pages) dan Applications › Event (/superadmin/events).
 */
class PageController extends Controller
{
    public function __construct(private readonly PageCategoryService $pages)
    {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $area = $this->area($request);

        $pages = PageCategory::query()
            ->forArea($area)
            ->with(['type:id,name,template', 'branch:id,name,company_id', 'branch.company:id,name'])
            ->withCount(['submissions', 'catalogOrders', 'quizAttempts', 'ticketOrders'])
            ->when($request->query('type'), fn ($q, $id) => $q->where('page_type_id', $id))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
                ->orWhereHas('branch.company', fn ($c) => $c->where('name', 'like', "%{$search}%"))))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('page.category.index', [
            'pages' => $pages,
            'types' => $this->typeQuery($area)->orderBy('name')->get(['id', 'name']),
            'routes' => 'superadmin.'.$area,
            'canManage' => $request->user()->canAccess('superadmin.'.$area, 'manage'),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($request, new PageCategory(['status' => PageCategory::STATUS_ACTIVE]));
    }

    public function store(PageRequest $request): RedirectResponse
    {
        $page = $this->pages->save(new PageCategory(), $request->branch(), $request->validated());
        $routes = 'superadmin.'.$this->area($request);

        if ($page->isTemplate(PageType::TEMPLATE_TICKET)) {
            return redirect()->route($routes.'.ticket.categories.index', $page)->with('success', "Event {$page->name} berhasil dibuat. Sekarang isi kategori tiketnya.");
        }

        return redirect()->route($routes.'.index')->with('success', "Page {$page->name} berhasil dibuat.");
    }

    public function edit(Request $request, PageCategory $page): View
    {
        return $this->form($request, $this->inArea($request, $page));
    }

    public function update(PageRequest $request, PageCategory $page): RedirectResponse
    {
        $this->pages->save($this->inArea($request, $page), $request->branch(), $request->validated());

        return redirect()->route('superadmin.'.$this->area($request).'.index')->with('success', "{$this->noun($request)} {$page->name} berhasil diperbarui.");
    }

    public function destroy(Request $request, PageCategory $page): RedirectResponse
    {
        $this->inArea($request, $page);

        try {
            $this->pages->delete($page);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('superadmin.'.$this->area($request).'.index')->with('success', "{$this->noun($request)} {$page->name} sudah dihapus.");
    }

    public function qrcode(Request $request, PageCategory $page): StreamedResponse
    {
        $this->inArea($request, $page);
        abort_unless($page->qrcodePath() && Storage::disk('public')->exists($page->qrcodePath()), 404);

        return Storage::disk('public')->download($page->qrcodePath(), 'qrcode-'.$page->slug.'.png');
    }

    private function form(Request $request, PageCategory $page): View
    {
        $area = $this->area($request);

        return view($page->exists ? 'page.category.edit' : 'page.category.create', [
            'page' => $page,
            'branches' => UserBranch::with('company:id,name')->orderBy('name')->get(['id', 'name', 'status', 'company_id'])
                ->sortBy(fn ($b) => $b->company?->name)->values(),
            'types' => $this->typeQuery($area)->orderBy('name')->get(['id', 'name', 'status', 'template']),
            'routes' => 'superadmin.'.$area,
        ]);
    }

    /** 'events' atau 'pages', dibaca dari nama route (superadmin.events.* / superadmin.pages.*). */
    private function area(Request $request): string
    {
        return Apps::pageArea($request->route()?->getName());
    }

    private function noun(Request $request): string
    {
        return $this->area($request) === Apps::EVENTS ? 'Event' : 'Page';
    }

    /** Event hanya bisa diubah lewat /superadmin/events, page lain lewat /superadmin/pages. */
    private function inArea(Request $request, PageCategory $page): PageCategory
    {
        abort_unless($page->app() === $this->area($request), 404);

        return $page;
    }

    private function typeQuery(string $area)
    {
        return PageType::query()->when(
            $area === Apps::EVENTS,
            fn ($q) => $q->whereIn('template', Apps::EVENT_TEMPLATES),
            fn ($q) => $q->whereNotIn('template', Apps::EVENT_TEMPLATES),
        );
    }
}

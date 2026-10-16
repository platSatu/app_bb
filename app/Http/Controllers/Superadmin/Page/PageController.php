<?php

namespace App\Http\Controllers\Superadmin\Page;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\Page\PageRequest;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\UserBranch;
use App\Services\Page\PageCategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Semua page milik semua user, dengan akses penuh. Form & tampilan sama dengan milik user. */
class PageController extends Controller
{
    private const ROUTES = 'superadmin.pages';

    public function __construct(private readonly PageCategoryService $pages)
    {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $pages = PageCategory::query()
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
            'types' => PageType::orderBy('name')->get(['id', 'name']),
            'routes' => self::ROUTES,
            'canManage' => $request->user()->canAccess('superadmin.pages', 'manage'),
        ]);
    }

    public function create(): View
    {
        return $this->form(new PageCategory(['status' => PageCategory::STATUS_ACTIVE]));
    }

    public function store(PageRequest $request): RedirectResponse
    {
        $page = $this->pages->save(new PageCategory(), $request->branch(), $request->validated());

        return $page->load('type')->isRegistration()
            ? redirect()->route(self::ROUTES.'.builder', $page)->with('success', "Page {$page->name} berhasil dibuat. Sekarang susun field form-nya.")
            : redirect()->route(self::ROUTES.'.index')->with('success', "Page {$page->name} berhasil dibuat.");
    }

    public function edit(PageCategory $page): View
    {
        return $this->form($page);
    }

    public function update(PageRequest $request, PageCategory $page): RedirectResponse
    {
        $this->pages->save($page, $request->branch(), $request->validated());

        return redirect()->route(self::ROUTES.'.index')->with('success', "Page {$page->name} berhasil diperbarui.");
    }

    public function destroy(PageCategory $page): RedirectResponse
    {
        try {
            $this->pages->delete($page);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route(self::ROUTES.'.index')->with('success', "Page {$page->name} sudah dihapus.");
    }

    public function qrcode(PageCategory $page): StreamedResponse
    {
        abort_unless($page->qrcodePath() && Storage::disk('public')->exists($page->qrcodePath()), 404);

        return Storage::disk('public')->download($page->qrcodePath(), 'qrcode-'.$page->slug.'.png');
    }

    private function form(PageCategory $page): View
    {
        return view($page->exists ? 'page.category.edit' : 'page.category.create', [
            'page' => $page,
            'branches' => UserBranch::with('company:id,name')->orderBy('name')->get(['id', 'name', 'status', 'company_id'])
                ->sortBy(fn ($b) => $b->company?->name)->values(),
            'types' => PageType::orderBy('name')->get(['id', 'name', 'status']),
            'routes' => self::ROUTES,
        ]);
    }
}

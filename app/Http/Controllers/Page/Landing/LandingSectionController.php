<?php

namespace App\Http\Controllers\Page\Landing;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Http\Requests\Page\Landing\LandingSectionRequest;
use App\Models\LandingSection;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Services\Ai\AiQuota;
use App\Services\Landing\LandingGenerator;
use App\Services\Landing\LandingSectionService;
use App\Support\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;

class LandingSectionController extends Controller
{
    use ResolvesPage;

    public function __construct(private readonly LandingSectionService $sections)
    {
    }

    public function index(Request $request, AiQuota $quota, string $page): View
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_LANDING);

        return view('page.landing.sections', [
            'page' => $model,
            'sections' => LandingSection::where('page_id', $model->getKey())->orderBy('sort_order')->get(),
            'branchPages' => $this->branchPages($model),
            'aiRemaining' => $quota->remaining($request->user()),
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function sync(LandingSectionRequest $request, string $page): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_LANDING, Menu::ABILITY_MANAGE);
        $this->sections->sync($model, $this->sections->normalize($model, $request->rows()));

        return back()->with('success', 'Isi landing page berhasil disimpan.');
    }

    /** Isi section dari AI. Hasil dikembalikan sebagai baris editor, BELUM disimpan. */
    public function generate(Request $request, AiQuota $quota, LandingGenerator $generator, string $page): JsonResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_LANDING, Menu::ABILITY_MANAGE);
        $description = $request->validate(['description' => ['required', 'string', 'min:15', 'max:1500']], [
            'description.min' => 'Ceritakan bisnis Anda sedikit lebih lengkap (minimal 15 karakter) supaya hasilnya bagus.',
        ])['description'];

        $consumed = false;

        try {
            $quota->consume($request->user());
            $consumed = true;
            $generated = $generator->generate($model->branch?->company?->name ?: $model->name, $description);
        } catch (RuntimeException $e) {
            // AI gagal = kuota dikembalikan.
            if ($consumed) {
                $quota->refund($request->user());
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }

        $html = collect($generated)->map(fn ($section, $i) => view('page.landing._section-row', [
            'i' => 'ai'.uniqid().$i,
            'row' => ['type' => $section['type'], 'status' => LandingSection::STATUS_ACTIVE, 'data' => $section['data']],
            'branchPages' => $this->branchPages($model),
            'page' => $model,
            'canManage' => true,
        ])->render())->implode('');

        return response()->json([
            'html' => $html,
            'count' => count($generated),
            'remaining' => $quota->remaining($request->user()),
        ]);
    }

    /** Preview tanpa menyimpan: dirender dengan template publik yang sama. Upload baru tampil setelah disimpan. */
    public function preview(LandingSectionRequest $request, string $page): Response
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_LANDING);

        $sections = collect($this->sections->normalize($model, $request->rows()))
            ->where('status', LandingSection::STATUS_ACTIVE)
            ->map(fn ($row) => (new LandingSection())->forceFill(['type' => $row['type'], 'data' => $row['data']]));

        return response()->view('page.public.show', [
            'page' => $model->load(['type', 'branch.company']),
            'open' => true,
            'sections' => $sections,
            'preview' => true,
        ]);
    }

    private function branchPages(PageCategory $page)
    {
        return PageCategory::where('branch_id', $page->branch_id)->whereKeyNot($page->getKey())->with('type:id,name')->orderBy('name')->get(['id', 'name', 'page_type_id']);
    }
}

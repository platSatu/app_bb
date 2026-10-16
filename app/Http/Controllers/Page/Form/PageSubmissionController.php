<?php

namespace App\Http\Controllers\Page\Form;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Models\PageSubmissionAnswer;
use App\Services\Page\PageSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PageSubmissionController extends Controller
{
    use ResolvesPage;

    public function index(Request $request, string $page): View
    {
        $model = $this->page($request, $page);
        $search = trim((string) $request->query('search'));

        $submissions = $model->submissions()
            ->with(['customer:id,name,phone,email', 'answers' => fn ($q) => $q->select('id', 'page_submission_id', 'label', 'type', 'value', 'file_name', 'sort_order')])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$search}%")
                ->orWhereHas('answers', fn ($a) => $a->where('value', 'like', "%{$search}%"))))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('page.submission.index', [
            'page' => $model,
            'submissions' => $submissions,
            'routes' => $this->routes($request),
        ]);
    }

    public function show(Request $request, string $page, string $submission): View
    {
        $model = $this->page($request, $page);

        return view('page.submission.show', [
            'page' => $model,
            'submission' => $model->submissions()->with(['answers', 'customer'])->findOrFail($submission),
            'routes' => $this->routes($request),
        ]);
    }

    public function file(Request $request, string $page, string $submission, string $answer): StreamedResponse
    {
        $item = PageSubmissionAnswer::query()
            ->where('page_submission_id', $this->page($request, $page)->submissions()->findOrFail($submission)->getKey())
            ->whereNotNull('file_path')
            ->findOrFail($answer);

        abort_unless(Storage::disk(PageSubmissionService::DISK)->exists($item->file_path), 404);

        return Storage::disk(PageSubmissionService::DISK)->download($item->file_path, $item->file_name);
    }
}

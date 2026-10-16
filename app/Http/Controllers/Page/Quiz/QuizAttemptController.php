<?php

namespace App\Http\Controllers\Page\Quiz;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Models\PageType;
use App\Models\QuizAttempt;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuizAttemptController extends Controller
{
    use ResolvesPage;

    public function index(Request $request, string $page): View
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_QUIZ);
        $search = trim((string) $request->query('search'));

        $attempts = QuizAttempt::where('page_id', $model->getKey())
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->when($request->query('state') === 'finished', fn ($q) => $q->whereNotNull('finished_at'))
            ->when($request->query('state') === 'unfinished', fn ($q) => $q->whereNull('finished_at'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('page.quiz.attempts.index', [
            'page' => $model,
            'attempts' => $attempts,
            'routes' => $this->routes($request),
        ]);
    }

    public function show(Request $request, string $page, string $attempt): View
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_QUIZ);

        return view('page.quiz.attempts.show', [
            'page' => $model,
            'attempt' => QuizAttempt::where('page_id', $model->getKey())->with(['answers', 'customer'])->findOrFail($attempt),
            'routes' => $this->routes($request),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Page;

use App\Http\Controllers\Controller;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\QuizAttempt;
use App\Rules\Turnstile;
use App\Services\Quiz\QuizService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Alur publik quiz: isi data diri -> kerjakan -> skor. Attempt yang aktif dipegang di session pengunjung. */
class PublicQuizController extends Controller
{
    public function __construct(private readonly QuizService $quiz)
    {
    }

    public function start(Request $request, string $slug): RedirectResponse
    {
        $page = $this->find($slug);

        $data = $request->validate([
            'website' => ['nullable', 'max:0'],
            'cf-turnstile-response' => [new Turnstile()],
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{8,20}$/'],
            'email' => ['nullable', 'email', 'max:255'],
        ], ['phone.regex' => 'Nomor WhatsApp belum benar.', 'website.max' => 'Gagal memulai quiz.'], ['name' => 'nama', 'phone' => 'nomor WhatsApp']);

        $attempt = $this->quiz->start($page, [
            'name' => trim($data['name']),
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
        ], $request->ip(), $request->userAgent());

        $request->session()->put($this->sessionKey($page), $attempt->getKey());

        return redirect()->route('page.quiz.play', $page->slug);
    }

    public function play(Request $request, string $slug): View|RedirectResponse
    {
        $page = $this->find($slug);
        $attempt = $this->activeAttempt($request, $page);

        if (! $attempt) {
            return redirect()->route('page.show', $page->slug);
        }

        return view('page.public.quiz-play', [
            'page' => $page,
            'attempt' => $attempt,
            // Kunci jawaban tidak ikut: is_correct & answer_keys disembunyikan di model, dan view tidak memakainya.
            'questions' => $page->quizQuestions()->active()->with('options:id,question_id,label,sort_order')->get(['id', 'page_id', 'type', 'question', 'points', 'sort_order']),
            'deadline' => $attempt->deadline($page->setting('quiz.time_limit')),
        ]);
    }

    public function submit(Request $request, string $slug): RedirectResponse
    {
        $page = $this->find($slug);
        $attempt = $this->activeAttempt($request, $page);

        if (! $attempt) {
            return redirect()->route('page.show', $page->slug);
        }

        $answers = $request->validate(['answers' => ['nullable', 'array', 'max:200'], 'answers.*' => ['nullable', 'string', 'max:5000']])['answers'] ?? [];
        $result = $this->quiz->submit($attempt, $answers);

        $request->session()->forget($this->sessionKey($page));

        return redirect()->route('page.show', $page->slug)->with('quiz_result', [
            'reference' => $result->reference,
            'score' => $result->score,
            'max' => $result->max_score,
            'late' => $result->is_late,
        ]);
    }

    private function activeAttempt(Request $request, PageCategory $page): ?QuizAttempt
    {
        $id = $request->session()->get($this->sessionKey($page));

        return $id ? QuizAttempt::where('page_id', $page->getKey())->whereNull('finished_at')->find($id) : null;
    }

    private function sessionKey(PageCategory $page): string
    {
        return 'quiz_attempt.'.$page->getKey();
    }

    private function find(string $slug): PageCategory
    {
        $page = PageCategory::with(['type', 'branch'])->where('slug', $slug)->firstOrFail();
        abort_unless($page->isOpen() && $page->branch?->isActive() && $page->isTemplate(PageType::TEMPLATE_QUIZ), 404);

        return $page;
    }
}

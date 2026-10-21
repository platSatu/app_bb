<?php

namespace App\Http\Controllers\Page\Quiz;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Http\Requests\Page\Quiz\QuizQuestionSyncRequest;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QuizQuestionController extends Controller
{
    use ResolvesPage;

    public function index(Request $request, string $page): View
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_QUIZ);

        return view('page.quiz.questions', [
            'page' => $model,
            'questions' => $model->quizQuestions()->with('options')->get(),
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
        ]);
    }

    /**
     * Soal & pilihan lama diperbarui di tempat (id tetap), yang baru dibuat, yang dihapus ikut dihapus.
     * Id pilihan tidak boleh berubah: peserta yang sedang mengerjakan mengirim id pilihan dari halaman yang sudah terbuka.
     */
    public function sync(QuizQuestionSyncRequest $request, string $page): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_QUIZ, Menu::ABILITY_MANAGE);

        DB::transaction(function () use ($model, $request) {
            PageCategory::whereKey($model->getKey())->lockForUpdate()->first();
            $existing = $model->quizQuestions()->get()->keyBy('id');
            $kept = [];

            foreach ($request->validated('questions') as $position => $row) {
                $question = $existing->get($row['id'] ?? '') ?? new QuizQuestion();
                $question->fill(collect($row)->only(['type', 'question', 'answer_keys', 'points', 'status'])->all());
                $question->page_id = $model->getKey();
                $question->sort_order = $position;
                $question->save();
                $kept[] = $question->getKey();

                $options = $question->options()->get()->keyBy('id');
                $keptOptions = [];

                foreach ($row['options'] ?? [] as $index => $option) {
                    // id hanya dipakai kalau memang pilihan milik soal ini.
                    $item = $options->get($option['id'] ?? '') ?? new QuizOption();
                    $item->fill(['label' => $option['label'], 'is_correct' => $option['is_correct']]);
                    $item->question_id = $question->getKey();
                    $item->sort_order = $index;
                    $item->save();
                    $keptOptions[] = $item->getKey();
                }

                $question->options()->whereKeyNot($keptOptions)->delete();
            }

            $model->quizQuestions()->whereKeyNot($kept)->delete();
        });

        return back()->with('success', 'Soal quiz berhasil disimpan.');
    }
}

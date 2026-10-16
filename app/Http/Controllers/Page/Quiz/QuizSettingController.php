<?php

namespace App\Http\Controllers\Page\Quiz;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Models\PageType;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuizSettingController extends Controller
{
    use ResolvesPage;

    public function edit(Request $request, string $page): View
    {
        return view('page.quiz.settings', [
            'page' => $this->templatePage($request, $page, PageType::TEMPLATE_QUIZ),
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function update(Request $request, string $page): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_QUIZ, Menu::ABILITY_MANAGE);

        $data = $request->validate([
            'time_limit' => ['nullable', 'integer', 'min:1', 'max:600'],
            'allow_retry' => ['boolean'],
        ], [], ['time_limit' => 'batas waktu']);

        $model->settings = [...($model->settings ?? []), 'quiz' => [
            'time_limit' => isset($data['time_limit']) ? (int) $data['time_limit'] : null,
            'allow_retry' => $request->boolean('allow_retry'),
        ]];
        $model->save();

        return back()->with('success', 'Pengaturan quiz berhasil disimpan.');
    }
}

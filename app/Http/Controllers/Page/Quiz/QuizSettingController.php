<?php

namespace App\Http\Controllers\Page\Quiz;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Models\PageType;
use App\Models\WaDevice;
use App\Services\Quiz\QuizService;
use App\Services\Subscription\AppAccess;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class QuizSettingController extends Controller
{
    use ResolvesPage;

    public function edit(Request $request, string $page): View
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_QUIZ);

        return view('page.quiz.settings', [
            'page' => $model,
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
            // Pengirim kode OTP dipilih dari device WhatsApp branch ini.
            'devices' => WaDevice::where('branch_id', $model->branch_id)->orderBy('name')->get(['id', 'name']),
            'waActive' => app(AppAccess::class)->branchActive($model->branch_id, 'whatsapp'),
        ]);
    }

    public function update(Request $request, string $page): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_QUIZ, Menu::ABILITY_MANAGE);

        $data = $request->validate([
            'time_limit' => ['nullable', 'integer', 'min:1', 'max:600'],
            'allow_retry' => ['boolean'],
            'navigation' => ['boolean'],
            // Gerbang kode: kosong = tanpa kunci, 'pin' = kode tetap dari owner, 'otp' = kode dikirim ke WhatsApp peserta.
            'gate' => ['nullable', Rule::in(['pin', 'otp'])],
            'pin' => ['nullable', 'string', 'min:4', 'max:12', 'required_if:gate,pin'],
            'device_id' => ['nullable', 'required_if:gate,otp', 'required_if:send_result,1', Rule::exists('wa_devices', 'id')->where('branch_id', $model->branch_id)],
            'code_minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
            'send_result' => ['boolean'],
            'result_text' => ['nullable', 'string', 'max:1000'],
            'grades' => ['nullable', 'array', 'max:'.QuizService::MAX_GRADES],
            'grades.*.min' => ['nullable', 'integer', 'min:0', 'max:100'],
            'grades.*.label' => ['nullable', 'string', 'max:20'],
            'grades.*.note' => ['nullable', 'string', 'max:120'],
        ], [
            'pin.required_if' => 'Isi PIN-nya dulu kalau quiz dikunci dengan PIN.',
            'device_id.required_if' => 'Pilih device WhatsApp yang akan mengirim pesannya.',
        ], ['time_limit' => 'batas waktu', 'pin' => 'PIN', 'device_id' => 'device pengirim', 'code_minutes' => 'masa berlaku kode', 'result_text' => 'teks hasil']);

        $gate = $data['gate'] ?? null;
        $sendResult = $request->boolean('send_result');

        // Seluruh isi settings.quiz ditulis ulang di sini, jadi kunci baru WAJIB ikut
        // disebut; kalau tidak, nilainya hilang diam-diam setiap kali pengaturan disimpan.
        $model->settings = [...($model->settings ?? []), 'quiz' => [
            'time_limit' => isset($data['time_limit']) ? (int) $data['time_limit'] : null,
            'allow_retry' => $request->boolean('allow_retry'),
            'navigation' => $request->boolean('navigation'),
            'gate' => $gate,
            // PIN tetap disimpan walau mode OTP: dipakai sebagai jalan masuk cadangan saat WhatsApp bermasalah.
            'pin' => $gate ? (trim((string) ($data['pin'] ?? '')) ?: null) : null,
            // Device dipakai bersama oleh OTP dan kirim hasil, jadi disimpan selama salah satunya menyala.
            'device_id' => $gate === 'otp' || $sendResult ? ($data['device_id'] ?? null) : null,
            'code_minutes' => $gate === 'otp' ? (int) ($data['code_minutes'] ?? 10) : null,
            'send_result' => $sendResult,
            'result_text' => $sendResult ? (trim((string) ($data['result_text'] ?? '')) ?: null) : null,
            'grades' => $this->grades($data['grades'] ?? []),
        ]];
        $model->save();

        return back()->with('success', 'Pengaturan quiz berhasil disimpan.');
    }

    /**
     * Baris grade yang diisi saja, diurutkan dari batas tertinggi. Urutan disimpan di sini
     * supaya QuizService tinggal mengambil yang pertama terpenuhi, tanpa mengurutkan lagi tiap kali.
     */
    private function grades(array $rows): array
    {
        return collect($rows)
            ->filter(fn ($row) => trim((string) ($row['label'] ?? '')) !== '')
            ->map(fn ($row) => [
                'min' => max(0, min(100, (int) ($row['min'] ?? 0))),
                'label' => trim((string) $row['label']),
                'note' => trim((string) ($row['note'] ?? '')) ?: null,
            ])
            ->sortByDesc('min')
            ->values()
            ->all();
    }
}

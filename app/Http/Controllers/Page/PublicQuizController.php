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
use RuntimeException;

/** Alur publik quiz: isi data diri -> kerjakan -> skor. Attempt yang aktif dipegang di session pengunjung. */
class PublicQuizController extends Controller
{
    public function __construct(private readonly QuizService $quiz)
    {
    }

    /** Kirim kode akses ke WhatsApp peserta, lalu kembali ke halaman yang sama. */
    public function code(Request $request, string $slug): RedirectResponse
    {
        $page = $this->find($slug);
        abort_unless($page->setting('quiz.gate') === 'otp', 404);

        $data = $request->validate([
            'website' => ['nullable', 'max:0'],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{8,20}$/'],
        ], ['phone.regex' => 'Nomor WhatsApp belum benar.', 'website.max' => 'Permintaan kode gagal.'], ['phone' => 'nomor WhatsApp']);

        // Isian lain dikembalikan lewat withInput supaya peserta tidak perlu mengetik ulang.
        return ($error = $this->quiz->sendCode($page, $data['phone']))
            ? back()->withInput()->withErrors(['code' => $error])
            : back()->withInput()->with('code_sent', 'Kode sudah dikirim ke WhatsApp Anda. Cek pesan masuk ya.');
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
            'code' => ['nullable', 'string', 'max:20'],
        ], ['phone.regex' => 'Nomor WhatsApp belum benar.', 'website.max' => 'Gagal memulai quiz.'], ['name' => 'nama', 'phone' => 'nomor WhatsApp']);

        // Dicek sebelum apa pun dibuat: kode salah tidak boleh meninggalkan attempt atau kontak baru.
        $this->quiz->assertCode($page, $data['phone'], $data['code'] ?? null);

        $attempt = $this->quiz->start($page, [
            'name' => trim($data['name']),
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
        ], $request->ip(), $request->userAgent());

        $request->session()->put($this->sessionKey($page), $attempt->getKey());

        // Quiz berbayar: soal baru dibuka setelah callback Duitku menyatakan lunas.
        if ($attempt->awaitsPayment()) {
            try {
                return redirect()->away($this->quiz->startPayment($attempt, $page));
            } catch (RuntimeException $e) {
                $request->session()->forget($this->sessionKey($page));

                return back()->withInput()->withErrors(['payment' => $e->getMessage()]);
            }
        }

        return redirect()->route('page.quiz.play', $page->slug);
    }

    public function play(Request $request, string $slug): View|RedirectResponse
    {
        $page = $this->find($slug);
        $attempt = $this->activeAttempt($request, $page);

        if (! $attempt) {
            return redirect()->route('page.show', $page->slug);
        }

        // Belum lunas: layar tunggu, bukan soal. Lunas ditentukan callback Duitku, bukan layar ini.
        if ($attempt->awaitsPayment()) {
            return view('page.public.quiz-pay', ['page' => $page, 'attempt' => $attempt]);
        }

        // Jam mengerjakan baru berjalan saat soal pertama benar-benar tampil, bukan saat attempt dibuat,
        // supaya peserta quiz berbayar tidak kehilangan menit selama menunggu pembayarannya masuk.
        if (! $attempt->opened_at) {
            $attempt->forceFill(['opened_at' => now()])->save();
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

        // Di luar transaksi submit(): pesan yang sudah terkirim tidak bisa ditarik kalau rollback.
        // Hasilnya dipakai layar hasil: terkirim = skor tidak diulang di layar.
        $viaWa = $this->quiz->sendResult($result);

        $request->session()->forget($this->sessionKey($page));

        return redirect()->route('page.show', $page->slug)->with('quiz_result', [
            'reference' => $result->reference,
            'score' => $result->score,
            'max' => $result->max_score,
            'late' => $result->is_late,
            'grade' => $this->quiz->band($result),
            'via_wa' => $viaWa,
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

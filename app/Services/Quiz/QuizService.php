<?php

namespace App\Services\Quiz;

use App\Models\PageCategory;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\Customer\CustomerService;
use App\Services\WhatsApp\WaGateway;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuizService
{
    // Toleransi jaringan setelah waktu habis, supaya kiriman otomatis di detik terakhir tetap dihitung.
    private const GRACE_SECONDS = 30;

    /** Masa berlaku kode OTP bila owner tidak mengisinya. */
    private const CODE_MINUTES = 10;

    /**
     * Batas permintaan kode. Satu kode = satu pesan WhatsApp = kuota & uang pemilik page,
     * jadi dibatasi dua lapis: per nomor (orang yang menekan berkali-kali) dan per page
     * (ramai-ramai menghabiskan kuota sebulan dalam semenit).
     */
    private const MAX_PER_PHONE = 3;   // per 10 menit
    private const MAX_PER_PAGE = 30;   // per jam

    public function __construct(private readonly CustomerService $customers, private readonly WaGateway $wa)
    {
    }

    /**
     * Kirim kode akses ke WhatsApp peserta.
     *
     * @return string|null pesan kesalahan untuk ditampilkan, atau null kalau terkirim.
     *
     * WaGateway yang menolak sendiri bila langganan WhatsApp cabang tidak aktif atau kuota pesan
     * habis, dan mengembalikan kuotanya kalau pengiriman gagal -- penghitungannya tidak diulang di sini.
     */
    public function sendCode(PageCategory $page, string $phone): ?string
    {
        $phone = User::normalizePhone($phone);
        $perPhone = 'quiz-code:'.$page->getKey().':'.$phone;
        $perPage = 'quiz-code-page:'.$page->getKey();

        if (RateLimiter::tooManyAttempts($perPhone, self::MAX_PER_PHONE)) {
            return 'Kode sudah dikirim beberapa kali ke nomor ini. Coba lagi beberapa menit lagi ya.';
        }

        if (RateLimiter::tooManyAttempts($perPage, self::MAX_PER_PAGE)) {
            return 'Permintaan kode sedang ramai. Coba lagi sebentar lagi ya.';
        }

        if (! $device = (string) $page->setting('quiz.device_id')) {
            return 'Pengirim WhatsApp belum dipilih pada quiz ini. Hubungi admin ya.';
        }

        $code = (string) random_int(100000, 999999);
        $minutes = $this->codeMinutes($page);

        $result = $this->wa->sendText($page->branch_id, [
            'device_id' => $device,
            'to' => $phone,
            'text' => "Kode akses *{$page->name}*: *{$code}*\n\nBerlaku {$minutes} menit. Jangan berikan kode ini ke siapa pun.",
            'client_ref' => 'quiz-code:'.Str::uuid(),
        ]);

        if (! ($result['success'] ?? false)) {
            return $result['message'] ?? 'Kode gagal dikirim. Hubungi admin ya.';
        }

        // Jatah baru dipakai setelah pesannya benar-benar terkirim, supaya kegagalan di sisi
        // gateway tidak ikut menghabiskan batas percobaan peserta.
        RateLimiter::hit($perPhone, 600);
        RateLimiter::hit($perPage, 3600);
        Cache::put($this->codeKey($page, $phone), $code, now()->addMinutes($minutes));

        return null;
    }

    /**
     * Periksa kode sebelum attempt dibuat. Dipanggil di awal start(), bukan di view.
     *
     * PIN tetap milik owner selalu diterima selama diisi -- sekaligus jadi jalan masuk cadangan
     * kalau WhatsApp sedang bermasalah, supaya quiz tidak mati total. Kode OTP sekali pakai.
     *
     * @throws ValidationException
     */
    public function assertCode(PageCategory $page, string $phone, ?string $code): void
    {
        if (! $gate = $page->setting('quiz.gate')) {
            return;
        }

        $code = trim((string) $code);
        $pin = trim((string) $page->setting('quiz.pin'));

        if ($pin !== '' && $code !== '' && hash_equals($pin, $code)) {
            return;
        }

        $saved = $gate === 'otp' && $code !== ''
            ? (string) Cache::get($key = $this->codeKey($page, User::normalizePhone($phone)), '')
            : '';

        if ($saved === '' || ! hash_equals($saved, $code)) {
            throw ValidationException::withMessages(['code' => 'Kode akses salah atau sudah kedaluwarsa.']);
        }

        Cache::forget($key);
    }

    /** Teks bawaan hasil quiz. Tanpa nama merek: bizbos itu SaaS, satu flow dipakai banyak pelanggan. */
    public const RESULT_TEXT = "Halo {nama} 👋\n\nTerima kasih sudah mengerjakan *{quiz}*.\n\nSkor Anda: *{skor}* dari {maks} ({persen}%)\nKode hasil: {referensi}";

    /**
     * Kirim hasil ke WhatsApp peserta. WAJIB dipanggil SETELAH submit() selesai, bukan di dalamnya:
     * submit() berjalan dalam transaksi, dan pesan yang sudah terkirim tidak bisa ditarik kalau rollback.
     *
     * Sengaja tidak pernah melempar error -- gagal kirim tidak boleh membuat peserta gagal melihat skornya.
     */
    public function sendResult(QuizAttempt $attempt): void
    {
        $page = $attempt->page ?? $attempt->load('page')->page;

        if (! $page?->setting('quiz.send_result') || ! $attempt->isFinished() || ! $attempt->phone) {
            return;
        }

        if (! $device = (string) $page->setting('quiz.device_id')) {
            return;
        }

        // Sekali per attempt: submit() yang dipanggil dua kali mengembalikan attempt yang sama,
        // dan peserta tidak boleh menerima hasil yang sama dua kali.
        if (! Cache::add('quiz-result-sent:'.$attempt->getKey(), true, now()->addDay())) {
            return;
        }

        $max = (int) $attempt->max_score;
        $text = strtr((string) ($page->setting('quiz.result_text') ?: self::RESULT_TEXT), [
            '{nama}' => $attempt->name,
            '{quiz}' => $page->name,
            '{skor}' => (string) (int) $attempt->score,
            '{maks}' => (string) $max,
            '{persen}' => (string) ($max > 0 ? (int) round($attempt->score / $max * 100) : 0),
            '{referensi}' => $attempt->reference,
        ]);

        $this->wa->sendText($page->branch_id, [
            'device_id' => $device,
            'to' => $attempt->phone,
            'text' => $text,
            'client_ref' => 'quiz-result:'.$attempt->getKey(),
        ]);
    }

    private function codeMinutes(PageCategory $page): int
    {
        return max(1, min(60, (int) $page->setting('quiz.code_minutes') ?: self::CODE_MINUTES));
    }

    private function codeKey(PageCategory $page, string $phone): string
    {
        return 'quiz-code-value:'.$page->getKey().':'.$phone;
    }

    /** @param  array{name: string, phone: string, email: ?string}  $participant */
    public function start(PageCategory $page, array $participant, ?string $ip, ?string $userAgent): QuizAttempt
    {
        return DB::transaction(function () use ($page, $participant, $ip, $userAgent) {
            // Kunci branch: dua tab yang mulai bersamaan tidak lolos aturan "1x per WA" dan kontak tidak kembar.
            $branch = UserBranch::whereKey($page->branch_id)->lockForUpdate()->firstOrFail();
            $phone = User::normalizePhone($participant['phone']);

            if (! $page->setting('quiz.allow_retry', false)
                && QuizAttempt::where('page_id', $page->getKey())->where('phone', $phone)->whereNotNull('finished_at')->exists()) {
                throw ValidationException::withMessages(['phone' => 'Nomor WhatsApp ini sudah pernah mengerjakan quiz ini.']);
            }

            $customer = $this->customers->resolve($branch, 'pages', $participant['name'], $phone, $participant['email'], 'Quiz: '.Str::limit($page->name, 200, ''));

            $attempt = new QuizAttempt();
            $attempt->forceFill([
                'page_id' => $page->getKey(),
                'company_id' => $branch->company_id,
                'customer_id' => $customer->getKey(),
                'reference' => $this->uniqueReference(),
                'name' => $participant['name'],
                'phone' => $phone,
                'email' => $participant['email'],
                'started_at' => now(),
                'ip_address' => $ip,
                'user_agent' => Str::limit((string) $userAgent, 250, ''),
            ])->save();

            return $attempt;
        });
    }

    /**
     * Nilai jawaban di server. Kunci jawaban hanya dibaca di sini.
     * Lewat batas waktu (+toleransi) = jawaban tetap disimpan, tapi tidak dihitung.
     *
     * @param  array<string, string|null>  $answers  [question_id => jawaban / option_id]
     */
    public function submit(QuizAttempt $attempt, array $answers): QuizAttempt
    {
        return DB::transaction(function () use ($attempt, $answers) {
            $locked = QuizAttempt::with('page')->whereKey($attempt->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isFinished()) {
                return $locked;
            }

            $deadline = $locked->deadline($locked->page->setting('quiz.time_limit'));
            $late = $deadline && now()->greaterThan($deadline->copy()->addSeconds(self::GRACE_SECONDS));

            $questions = $locked->page->quizQuestions()->active()->with('options')->get();
            $score = $max = $correct = 0;

            foreach ($questions as $position => $question) {
                $value = $answers[$question->getKey()] ?? null;
                $value = is_string($value) ? Str::limit(trim($value), 5000, '') : null;
                [$text, $isCorrect] = $this->grade($question, $value);

                if ($question->isScored()) {
                    $max += $question->points;
                }

                $earned = ! $late && $isCorrect ? $question->points : 0;
                $score += $earned;
                $correct += ! $late && $isCorrect ? 1 : 0;

                $answer = new QuizAnswer();
                $answer->forceFill([
                    'attempt_id' => $locked->getKey(),
                    'question_id' => $question->getKey(),
                    'type' => $question->type,
                    'question' => $question->question,
                    'answer' => $text,
                    'is_correct' => $question->isScored() ? (bool) $isCorrect : null,
                    'points' => $earned,
                    'sort_order' => $position,
                ])->save();
            }

            $locked->forceFill([
                'score' => $score,
                'max_score' => $max,
                'correct_count' => $correct,
                'is_late' => $late,
                'finished_at' => now(),
            ])->save();

            return $locked;
        });
    }

    /** @return array{0: ?string, 1: ?bool} [teks jawaban untuk disimpan, benar?] */
    private function grade(QuizQuestion $question, ?string $value): array
    {
        return match ($question->type) {
            QuizQuestion::TYPE_SINGLE => (function () use ($question, $value) {
                // Hanya pilihan milik soal ini yang diterima.
                $option = $question->options->firstWhere('id', $value);

                return [$option?->label, (bool) $option?->is_correct];
            })(),
            QuizQuestion::TYPE_TEXT => [$value, $question->matchesText($value)],
            default => [$value, null],
        };
    }

    private function uniqueReference(): string
    {
        do {
            $reference = 'QZ'.now()->format('ymd').Str::upper(Str::random(6));
        } while (QuizAttempt::where('reference', $reference)->exists());

        return $reference;
    }
}

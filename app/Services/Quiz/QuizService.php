<?php

namespace App\Services\Quiz;

use App\Models\PageCategory;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\Customer\CustomerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class QuizService
{
    // Toleransi jaringan setelah waktu habis, supaya kiriman otomatis di detik terakhir tetap dihitung.
    private const GRACE_SECONDS = 30;

    public function __construct(private readonly CustomerService $customers)
    {
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

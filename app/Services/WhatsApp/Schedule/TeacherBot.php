<?php

namespace App\Services\WhatsApp\Schedule;

use App\Jobs\SendWaNotification;
use App\Models\ScheduleAttendance;
use App\Models\ScheduleSession;
use App\Models\ScheduleTeacher;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\WaChat;
use App\Models\WaScheduleConversation as Conversation;
use App\Services\Schedule\RescheduleService;
use App\Services\Schedule\ScheduleSessionService;
use App\Services\WhatsApp\Bot\BotReply;
use App\Support\ScheduleBotText as Text;
use App\Support\ScheduleNotice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Bagian pengajar dari chatbot WhatsApp Jadwal (lewat ScheduleBot):
 *  - rekap H-1 bisa dibalas: 1 siap, 2 berhalangan -> admin dikabari (yang mengatur tetap admin);
 *  - absen murid lewat WhatsApp, PENGGANTI scan kartu murid: baru dikirim setelah pengajar absen datang
 *    (scan QR tempat di lokasi, atau "mulai" untuk kelas online) dan sesinya sudah dimulai. Murid yang sudah
 *    discan QR tidak ditanya lagi. Begitu semua murid punya status, sesi ditutup otomatis lewat complete();
 *  - kelas online: pengajar dikirimi pesan menjelang sesi, balas 1 = absen datang.
 * Setiap balasan dicek ulang: nomor = nomor pengajar sesi itu, sesi masih terjadwal & masih dalam waktunya.
 */
class TeacherBot
{
    use ConversationSteps;

    /** Kelas online: "mulai" bisa ditekan sejak sekian menit sebelum sesi. */
    public const ONLINE_EARLY_MINUTES = 10;

    /** Balasan absen masih diterima sampai sekian jam setelah sesi berakhir. */
    public const ATTEND_GRACE_HOURS = 2;

    public function __construct(private readonly ScheduleSessionService $sessions, private readonly ReplyIntent $intent)
    {
    }

    /** Bot nyala dan nomor ini sedang tidak di tengah percakapan lain. */
    public function canPrompt(string $branchId, ?string $phone): bool
    {
        $phone = User::normalizePhone($phone);

        return $phone !== '' && ScheduleBot::enabled($branchId)
            && ! Conversation::where('branch_id', $branchId)->where('phone', $phone)->active()->exists();
    }

    /** Dipanggil setelah rekap H-1 (yang sudah memuat pilihan 1 / 2) diantrekan ke pengajar. */
    public function openRecap(ScheduleTeacher $teacher, Collection $sessions): void
    {
        $phone = User::normalizePhone($teacher->phone);
        $first = $sessions->sortBy('start_at')->first();

        if ($phone === '' || ! $first?->start_at->isFuture()) {
            return;
        }

        DB::transaction(fn () => $this->start($first->branch_id, $phone, Conversation::ROLE_TEACHER, Conversation::STEP_RECAP,
            ['session_ids' => $sessions->modelKeys()], $first->start_at));
    }

    /**
     * Daftar murid yang belum diabsen untuk satu sesi. Hanya kalau pengajar sudah absen datang, sesi sudah dimulai,
     * dan masih ada murid "belum diisi". Sekali per sesi (penanda di cache); false = tidak dikirim (dicoba lagi nanti).
     */
    public function promptAttendance(ScheduleSession $session): bool
    {
        $session->loadMissing(['teacher:id,name,phone,status', 'grade.category.subject', 'attendances.student:id,name']);
        $phone = User::normalizePhone($session->teacher?->phone);
        $names = $session->attendances->where('status', ScheduleAttendance::STATUS_PENDING)->pluck('student.name')->filter()->values()->all();
        $key = 'wa-absen-prompt:'.$session->getKey();

        if (Cache::has($key) || $names === [] || ! $this->attendanceOpen($session) || ! $session->start_at->isPast() || ! $this->canPrompt($session->branch_id, $phone)) {
            return false;
        }

        return $this->send($session, $phone, Conversation::STEP_ATTEND, Text::attendPrompt($session->teacher->name, $session, $names), $key);
    }

    /** Kelas online menjelang mulai: minta pengajar menekan 1 untuk absen datang. */
    public function promptOnlineStart(ScheduleSession $session): bool
    {
        $session->loadMissing(['teacher:id,name,phone,status', 'room:id,is_online', 'grade.category.subject']);
        $phone = User::normalizePhone($session->teacher?->phone);
        $key = 'wa-online-start:'.$session->getKey();

        if (Cache::has($key) || ! $session->room?->is_online || $session->teacher_checked_in_at || $session->status !== ScheduleSession::STATUS_SCHEDULED
            || $session->end_at->isPast() || ! $this->canPrompt($session->branch_id, $phone)) {
            return false;
        }

        return $this->send($session, $phone, Conversation::STEP_ONLINE_START, Text::onlineStartPrompt($session->teacher->name, $session), $key);
    }

    /** Semua murid sudah punya status & sesi sudah dimulai: tutup lewat complete() (honor dihitung seperti di website). */
    public function closeIfReady(ScheduleSession $session): bool
    {
        $session = $session->fresh(['attendances']);

        if (! $session || $session->status !== ScheduleSession::STATUS_SCHEDULED || $session->start_at->isFuture()
            || $session->attendances->isEmpty() || $session->attendances->contains('status', ScheduleAttendance::STATUS_PENDING)) {
            return false;
        }

        try {
            $this->sessions->complete($session, $session->attendances->pluck('status', 'student_id')->all(), null, ScheduleTeacher::find($session->teacher_id)?->user);
        } catch (RuntimeException) {
            return false;
        }

        return true;
    }

    public function handle(Conversation $conversation, WaChat $chat, string $text, string $raw): ?BotReply
    {
        try {
            return in_array($conversation->step, [Conversation::STEP_RECAP, Conversation::STEP_RECAP_PICK], true)
                ? $this->recap($conversation, $chat, $text)
                : $this->session($conversation, $chat, $text);
        } catch (RuntimeException) {
            return $this->finish($conversation, Text::expired());
        }
    }

    // ------------------------------------------------------------------ Rekap H-1

    private function recap(Conversation $conversation, WaChat $chat, string $text): ?BotReply
    {
        $sessions = ScheduleSession::with(['teacher:id,name,phone', 'grade.category.subject', 'attendances.student:id,name'])
            ->whereKey((array) $conversation->context('session_ids', []))
            ->where('branch_id', $conversation->branch_id)
            ->where('status', ScheduleSession::STATUS_SCHEDULED)
            ->where('start_at', '>', now())
            ->orderBy('start_at')->get()
            ->filter(fn (ScheduleSession $s) => User::normalizePhone($s->teacher?->phone) === $conversation->phone)
            ->values();

        if ($sessions->isEmpty()) {
            return $this->finish($conversation, Text::expired());
        }

        $teacher = $sessions->first()->teacher;

        if ($conversation->step === Conversation::STEP_RECAP) {
            return match ($this->intent->choice($chat, $text, 2, true, 'Apakah pengajar siap mengajar sesuai jadwal besok?')) {
                1 => $this->finish($conversation, Text::recapThanks($teacher->name)),
                2 => $sessions->count() === 1
                    ? $this->finish($conversation, Text::recapReported(), fn () => $this->reportUnavailable($teacher, $sessions))
                    : $this->next($conversation, Conversation::STEP_RECAP_PICK, [], Text::recapPick($sessions)),
                default => $this->invalid($conversation, Text::teacherOptions()),
            };
        }

        $picked = $this->numbers($text, $sessions->count() + 1);

        if ($picked === []) {
            return $this->invalid($conversation, 'Balas nomor jadwalnya ya, contoh: 1 atau 1,3.');
        }

        $chosen = in_array($sessions->count() + 1, $picked, true) ? $sessions : $sessions->only(array_map(fn ($n) => $n - 1, $picked))->values();

        return $this->finish($conversation, Text::recapReported(), fn () => $this->reportUnavailable($teacher, $chosen));
    }

    /** Dicatat di sesi (terlihat di website) dan admin dikabari; perubahannya tetap diatur admin. */
    private function reportUnavailable(ScheduleTeacher $teacher, Collection $sessions): void
    {
        ScheduleSession::whereKey($sessions->modelKeys())->update(['teacher_unavailable_at' => now()]);

        $branch = UserBranch::with('user:id,handphone')->find($sessions->first()->branch_id);
        SendWaNotification::queue($branch?->getKey(), ScheduleNotice::BOT, RescheduleService::adminPhone($branch), Text::adminTeacherUnavailable($teacher->name, $sessions));
    }

    // ------------------------------------------------------------------ Absen sesi (murid & kelas online)

    private function session(Conversation $conversation, WaChat $chat, string $text): ?BotReply
    {
        $session = ScheduleSession::with(['teacher:id,name,phone,status', 'room:id,is_online', 'grade.category.subject', 'attendances.student:id,name'])
            ->where('branch_id', $conversation->branch_id)->find($conversation->context('session_id'));

        if (! $session || User::normalizePhone($session->teacher?->phone) !== $conversation->phone || $session->teacher?->status !== ScheduleTeacher::STATUS_ACTIVE
            || $session->status !== ScheduleSession::STATUS_SCHEDULED) {
            return $this->finish($conversation, Text::expired());
        }

        if ($conversation->step === Conversation::STEP_ONLINE_START) {
            return $this->onlineStart($conversation, $chat, $session, $text);
        }

        if (! $this->attendanceOpen($session)) {
            return $this->finish($conversation, Text::expired());
        }

        $pending = $session->attendances->where('status', ScheduleAttendance::STATUS_PENDING)->values();

        if ($pending->isEmpty()) {
            return $this->finish($conversation, Text::attendAllDone(), fn () => $this->closeIfReady($session));
        }

        return match ($conversation->step) {
            Conversation::STEP_ATTEND => match ($this->intent->choice($chat, $text, 2, true, 'Apakah semua murid hadir di les ini?')) {
                1 => $this->save($conversation, $session, [], ScheduleAttendance::STATUS_PRESENT),
                2 => $this->next($conversation, Conversation::STEP_ATTEND_PICK, ['pending_ids' => $pending->modelKeys(), 'absent_ids' => []],
                    Text::attendPick($pending->pluck('student.name')->all()), $this->minutesLeft($session)),
                default => $this->invalid($conversation, Text::teacherOptions()),
            },
            Conversation::STEP_ATTEND_PICK => $this->onPick($conversation, $session, $text),
            Conversation::STEP_ATTEND_REASON => $this->onReason($conversation, $session, $text),
            Conversation::STEP_ATTEND_CONFIRM => match ($this->number($text)) {
                1 => $this->save($conversation, $session, (array) $conversation->context('absent_ids', []), (string) $conversation->context('absent_status')),
                2 => $this->next($conversation, Conversation::STEP_ATTEND, ['absent_ids' => []],
                    Text::attendPrompt($session->teacher->name, $session, $pending->pluck('student.name')->all()), $this->minutesLeft($session)),
                default => $this->invalid($conversation, 'Balas *1* untuk simpan, *2* untuk ulangi.'),
            },
            default => $this->finish($conversation, null),
        };
    }

    private function onPick(Conversation $conversation, ScheduleSession $session, string $text): ?BotReply
    {
        $ids = (array) $conversation->context('pending_ids', []);
        $picked = $this->numbers($text, count($ids));

        if ($picked === []) {
            return $this->invalid($conversation, 'Balas nomor muridnya ya, contoh: 1 atau 1,3.');
        }

        $absent = array_values(array_intersect_key($ids, array_flip(array_map(fn ($n) => $n - 1, $picked))));
        $names = $session->attendances->whereIn('id', $absent)->pluck('student.name')->all();

        return $this->next($conversation, Conversation::STEP_ATTEND_REASON, ['absent_ids' => $absent], Text::attendReason($names), $this->minutesLeft($session));
    }

    private function onReason(Conversation $conversation, ScheduleSession $session, string $text): ?BotReply
    {
        $status = match ($this->number($text)) {
            1 => ScheduleAttendance::STATUS_EXCUSED,
            2 => ScheduleAttendance::STATUS_ABSENT,
            default => null,
        };

        if (! $status) {
            return $this->invalid($conversation, "Balas *1* kalau sakit / izin, *2* kalau tanpa keterangan.");
        }

        $absent = (array) $conversation->context('absent_ids', []);
        $pending = $session->attendances->where('status', ScheduleAttendance::STATUS_PENDING);

        return $this->next($conversation, Conversation::STEP_ATTEND_CONFIRM, ['absent_status' => $status], Text::attendConfirm(
            $pending->whereNotIn('id', $absent)->pluck('student.name')->values()->all(),
            $pending->whereIn('id', $absent)->pluck('student.name')->values()->all(),
            $status === ScheduleAttendance::STATUS_EXCUSED,
        ), $this->minutesLeft($session));
    }

    /**
     * Simpan absen: murid yang masih "belum diisi" saja (yang sudah discan QR tidak disentuh). $absentIds = id absensi
     * yang tidak hadir dari daftar yang ditampilkan; id dari sesi lain tidak berpengaruh karena hanya absensi sesi ini yang dibaca.
     */
    private function save(Conversation $conversation, ScheduleSession $session, array $absentIds, string $absentStatus): ?BotReply
    {
        if (! in_array($absentStatus, [ScheduleAttendance::STATUS_PRESENT, ScheduleAttendance::STATUS_EXCUSED, ScheduleAttendance::STATUS_ABSENT], true)) {
            return $this->finish($conversation, Text::expired());
        }

        $present = DB::transaction(function () use ($session, $absentIds, $absentStatus) {
            $locked = ScheduleSession::whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== ScheduleSession::STATUS_SCHEDULED || ! $locked->teacher_checked_in_at) {
                throw new RuntimeException('Sesi sudah berubah.');
            }

            $present = [];

            foreach (ScheduleAttendance::where('session_id', $locked->getKey())->where('status', ScheduleAttendance::STATUS_PENDING)->lockForUpdate()->get() as $attendance) {
                $status = in_array($attendance->getKey(), $absentIds, true) ? $absentStatus : ScheduleAttendance::STATUS_PRESENT;
                $attendance->forceFill(['status' => $status, 'marked_via' => 'whatsapp', 'marked_at' => now()])->save();
                $status === ScheduleAttendance::STATUS_PRESENT && $present[] = $attendance;
            }

            return $present;
        });

        // Kabar hadir ke orang tua & admin, sama seperti scan kartu (sakelarnya masing-masing).
        foreach ($present as $attendance) {
            SendWaNotification::checkedIn($attendance);
        }

        $conversation->delete();
        $closed = $this->closeIfReady($session);
        $counts = ScheduleAttendance::where('session_id', $session->getKey())->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status')->all();

        // Sesi berikutnya yang juga sudah dimulai & menunggu absen (mis. dua kelas berurutan).
        DB::afterCommit(fn () => $this->promptNext($session));

        return new BotReply(Text::attendSaved($counts, $closed));
    }

    private function onlineStart(Conversation $conversation, WaChat $chat, ScheduleSession $session, string $text): ?BotReply
    {
        if (! $session->room?->is_online || $session->teacher_checked_in_at || $session->end_at->isPast()) {
            return $this->finish($conversation, Text::expired());
        }

        if ($this->intent->choice($chat, $text, 1, true, 'Apakah pengajar sudah mulai mengajar kelas online?') !== 1) {
            return $this->invalid($conversation, 'Balas *1* saat Kakak mulai mengajar ya.');
        }

        if ($session->start_at->copy()->subMinutes(self::ONLINE_EARLY_MINUTES)->isFuture()) {
            return new BotReply(Text::onlineTooEarly(self::ONLINE_EARLY_MINUTES));
        }

        $this->sessions->onlineCheckIn($session);
        $conversation->delete();
        DB::afterCommit(fn () => $this->promptAttendance($session->refresh()));

        return new BotReply(Text::onlineStarted());
    }

    private function promptNext(ScheduleSession $done): void
    {
        ScheduleSession::where('teacher_id', $done->teacher_id)->whereKeyNot($done->getKey())
            ->where('status', ScheduleSession::STATUS_SCHEDULED)->whereNotNull('teacher_checked_in_at')
            ->where('start_at', '<=', now())->where('end_at', '>=', now()->subHours(self::ATTEND_GRACE_HOURS))
            ->orderBy('start_at')->get()
            ->first(fn (ScheduleSession $s) => $this->promptAttendance($s));
    }

    private function send(ScheduleSession $session, string $phone, string $step, string $text, string $key): bool
    {
        $expires = $step === Conversation::STEP_ONLINE_START ? $session->end_at : $session->end_at->copy()->addHours(self::ATTEND_GRACE_HOURS);
        $conversation = DB::transaction(fn () => $this->start($session->branch_id, $phone, Conversation::ROLE_TEACHER, $step, ['session_id' => $session->getKey()], $expires));

        if (! SendWaNotification::queue($session->branch_id, ScheduleNotice::BOT, $phone, $text)) {
            $conversation->delete();

            return false;
        }

        Cache::put($key, true, now()->addDay());

        return true;
    }

    /** Absen murid lewat WhatsApp hanya setelah pengajar absen datang, sampai ATTEND_GRACE_HOURS jam setelah sesi berakhir. */
    private function attendanceOpen(ScheduleSession $session): bool
    {
        return $session->status === ScheduleSession::STATUS_SCHEDULED && $session->teacher_checked_in_at !== null
            && $session->end_at->copy()->addHours(self::ATTEND_GRACE_HOURS)->isFuture();
    }

    private function minutesLeft(ScheduleSession $session): int
    {
        return max(5, (int) now()->diffInMinutes($session->end_at->copy()->addHours(self::ATTEND_GRACE_HOURS)));
    }

    /** "1,3" / "1 3" / "1 dan 3" -> [1, 3]; angka di luar 1..$max membuat seluruh jawaban tidak sah. */
    private function numbers(string $text, int $max): array
    {
        preg_match_all('/\d{1,2}/', $text, $m);
        $numbers = array_values(array_unique(array_map('intval', $m[0])));

        if ($numbers === [] || collect($numbers)->contains(fn ($n) => $n < 1 || $n > $max)
            || trim(preg_replace('/[\d\s,;.&+]|dan/u', '', $text)) !== '') {
            return [];
        }

        return $numbers;
    }
}

<?php

namespace App\Services\WhatsApp\Schedule;

use App\Jobs\SendWaNotification;
use App\Models\ScheduleAttendance;
use App\Models\ScheduleRescheduleRequest;
use App\Models\ScheduleSession;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\WaChat;
use App\Models\WaScheduleConversation as Conversation;
use App\Services\Schedule\RescheduleService;
use App\Services\Subscription\AppAccess;
use App\Services\WhatsApp\Bot\BotReply;
use App\Services\WhatsApp\Bot\FlowEngine;
use App\Support\ScheduleBotText as Text;
use App\Support\ScheduleNotice;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Chatbot WhatsApp Jadwal. Dipanggil BotRouter paling awal, sebelum chatbot flow / auto reply / AI.
 *
 * Bot ini TIDAK PERNAH memulai percakapan dari pesan masuk biasa: ia hanya menjawab nomor yang sedang punya
 * percakapan aktif (WaScheduleConversation), dan percakapan itu hanya dibuat sistem:
 *  - murid / orang tua: saat pengingat les terkirim ke nomor murid itu (openReminder);
 *  - admin (nomor owner branch): saat ada pengajuan ganti hari (presentToAdmin), atau owner mengetik "pengajuan" / "menu" (AdminMenu);
 *  - pengajar: rekap H-1, absen murid setelah pengajar absen datang, dan "mulai" kelas online (TeacherBot).
 * Selain itu pesan diteruskan ke alur lama, jadi chat pribadi tidak tersentuh.
 *
 * Pengaman:
 *  - setiap langkah memeriksa ulang data di database (nomor = nomor murid / owner, sesi masih terjadwal, pengajuan masih menunggu);
 *  - pilihan jadwal dihitung ulang saat diajukan (RescheduleService::submit), bukan dipercaya dari percakapan;
 *  - perubahan data lewat RescheduleService -> ScheduleSessionService, aturan sama dengan website;
 *  - sakelar Schedules › Notifikasi › Chatbot WhatsApp mati = bot diam seluruhnya.
 */
class ScheduleBot
{
    use ConversationSteps;

    private const ADMIN_IDLE_HOURS = 24;

    private const ADMIN_KEYWORDS = ['pengajuan', 'ganti hari'];

    public function __construct(
        private readonly RescheduleService $reschedules,
        private readonly ReplyIntent $intent,
        private readonly TeacherBot $teachers,
        private readonly AdminMenu $menu,
    )
    {
    }

    /** Sakelar bot nyala, paket Jadwal & WhatsApp branch aktif. */
    public static function enabled(UserBranch|string|null $branch): bool
    {
        $branch = is_string($branch) ? UserBranch::find($branch) : $branch;

        return $branch !== null && $branch->waNotifies(ScheduleNotice::BOT)
            && app(AppAccess::class)->branchActive($branch->getKey(), 'schedules');
    }

    /** Pengingat boleh dibuat bisa-dibalas: bot nyala dan nomor ini tidak sedang di tengah percakapan lain. */
    public function canPrompt(string $branchId, ?string $phone): bool
    {
        $phone = User::normalizePhone($phone);

        return $phone !== '' && self::enabled($branchId)
            && ! Conversation::where('branch_id', $branchId)->where('phone', $phone)->active()->exists();
    }

    /** Dipanggil setelah pengingat les (yang sudah memuat pilihan 1 / 2) diantrekan ke nomor murid. */
    public function openReminder(ScheduleAttendance $attendance, ScheduleSession $session, bool $soon): void
    {
        $phone = User::normalizePhone($attendance->student?->phone);

        if ($phone === '' || ! $session->start_at->isFuture()) {
            return;
        }

        DB::transaction(function () use ($attendance, $session, $soon, $phone) {
            $this->start($session->branch_id, $phone, Conversation::ROLE_STUDENT, Conversation::STEP_CONFIRM,
                ['attendance_id' => $attendance->getKey(), 'soon' => $soon], $session->start_at);
        });
    }

    /** Pengajuan baru: kalau admin sedang tidak menjawab pengajuan lain, langsung dikirimi yang paling lama menunggu. */
    public function presentToAdmin(string $branchId): void
    {
        $branch = UserBranch::with('user:id,handphone')->find($branchId);
        $phone = RescheduleService::adminPhone($branch);

        if ($phone === '' || ! self::enabled($branch)) {
            return;
        }

        $text = DB::transaction(function () use ($branchId, $phone) {
            if (Conversation::where('branch_id', $branchId)->where('phone', $phone)->active()->lockForUpdate()->exists()) {
                return null; // pengajuan berikutnya dikirim setelah yang sekarang dijawab
            }

            return $this->nextForAdmin($branchId, $phone);
        });

        if ($text !== null && ! SendWaNotification::queue($branchId, ScheduleNotice::BOT, $phone, $text)) {
            Conversation::where('branch_id', $branchId)->where('phone', $phone)->delete();
        }
    }

    /**
     * Jawaban untuk satu pesan masuk, atau null = bukan urusan bot Jadwal (diteruskan ke flow / auto reply / AI).
     * $text = isi yang sudah dinormalisasi BotRouter (huruf kecil), $raw = isi asli (untuk alasan).
     */
    public function reply(WaChat $chat, string $text, string $raw): ?BotReply
    {
        $phone = User::normalizePhone($chat->phone);

        if ($phone === '' || ! self::enabled($chat->branch_id)) {
            return null;
        }

        return DB::transaction(function () use ($chat, $phone, $text, $raw) {
            $conversation = Conversation::where('branch_id', $chat->branch_id)->where('phone', $phone)->lockForUpdate()->first();

            if ($conversation?->expires_at->isPast()) {
                $conversation->delete();
                $conversation = null;
            }

            if (! $conversation) {
                return $this->adminShortcut($chat->branch_id, $phone, $text);
            }

            if ($text === Text::HANDOVER_KEY) {
                $conversation->delete();

                return new BotReply(Text::handover(), true, FlowEngine::HANDOVER_MINUTES);
            }

            return match ($conversation->role) {
                Conversation::ROLE_ADMIN => $this->admin($conversation, $chat, $text, $raw),
                Conversation::ROLE_TEACHER => $this->teachers->handle($conversation, $chat, $text, $raw),
                default => $this->student($conversation, $chat, $text, $raw),
            };
        });
    }

    // ------------------------------------------------------------------ Murid / orang tua

    private function student(Conversation $conversation, WaChat $chat, string $text, string $raw): ?BotReply
    {
        $attendance = ScheduleAttendance::with(['student', 'session.teacher:id,name', 'session.grade.category.subject'])->find($conversation->context('attendance_id'));
        $session = $attendance?->session;

        // Hanya absensi milik nomor ini, di branch ini, yang sesinya masih akan datang.
        if (! $attendance || $session?->branch_id !== $conversation->branch_id || $session->status !== ScheduleSession::STATUS_SCHEDULED
            || ! $session->start_at->isFuture() || User::normalizePhone($attendance->student?->phone) !== $conversation->phone) {
            $conversation->delete();

            return new BotReply(Text::expired());
        }

        $student = $attendance->student;
        $soon = (bool) $conversation->context('soon');

        try {
            return match ($conversation->step) {
                Conversation::STEP_CONFIRM => $this->onConfirm($conversation, $chat, $attendance, $text),
                Conversation::STEP_REASON => $this->onReason($conversation, $attendance, $raw, $soon),
                Conversation::STEP_RESCHEDULE_ASK => $this->onRescheduleAsk($conversation, $chat, $attendance, $text),
                Conversation::STEP_SLOT => $this->onSlot($conversation, $attendance, $text),
                default => $this->finish($conversation, null),
            };
        } catch (RuntimeException) {
            // Data berubah di tengah jalan (mis. sesi dipindah admin): tutup dengan sopan.
            return $this->finish($conversation, Text::expired());
        }
    }

    private function onConfirm(Conversation $conversation, WaChat $chat, ScheduleAttendance $attendance, string $text): ?BotReply
    {
        $question = 'Apakah '.$attendance->student->name.' bisa hadir les '.Text::when($attendance->session).'?';

        return match ($this->intent->choice($chat, $text, 2, true, $question)) {
            1 => $this->finish($conversation, Text::confirmed($attendance->student), fn () => $this->reschedules->confirm($attendance)),
            2 => $this->next($conversation, Conversation::STEP_REASON, [], Text::askReason($attendance->student)),
            default => $this->invalid($conversation, Text::confirmOptions()),
        };
    }

    private function onReason(Conversation $conversation, ScheduleAttendance $attendance, string $raw, bool $soon): ?BotReply
    {
        $reason = trim(preg_replace('/\s+/u', ' ', $raw));

        if (mb_strlen($reason) < 3) {
            return $this->invalid($conversation, Text::reasonTooShort(), bare: true);
        }

        $this->reschedules->excuse($attendance, $reason);
        $attendance->refresh();

        // Pengingat sesaat sebelum les: sudah terlalu mepet untuk ganti hari.
        if ($soon) {
            return $this->finish($conversation, Text::excusedSoon($attendance->student));
        }

        return $this->next($conversation, Conversation::STEP_RESCHEDULE_ASK, ['reason' => mb_substr($reason, 0, 255)], Text::askReschedule($attendance->student, $attendance->session));
    }

    private function onRescheduleAsk(Conversation $conversation, WaChat $chat, ScheduleAttendance $attendance, string $text): ?BotReply
    {
        return match ($this->intent->choice($chat, $text, 2, false, '')) {
            1 => $this->offerSlots($conversation, $attendance, null),
            2 => $this->finish($conversation, Text::excusedOnly($attendance->student)),
            default => $this->invalid($conversation, Text::yesNoOptions()),
        };
    }

    private function onSlot(Conversation $conversation, ScheduleAttendance $attendance, string $text): ?BotReply
    {
        $slots = (array) $conversation->context('slots', []);
        $number = $this->number($text);

        if ($number === count($slots) + 1) {
            $conversation->delete();

            return new BotReply(Text::handover(), true, FlowEngine::HANDOVER_MINUTES);
        }

        if ($number < 1 || $number > count($slots)) {
            return $this->invalid($conversation, 'Balas dengan angka 1 sampai '.(count($slots) + 1).' ya.');
        }

        try {
            $request = $this->reschedules->submit($attendance, $slots[$number - 1], $conversation->context('reason'));
        } catch (RuntimeException) {
            return $this->offerSlots($conversation, $attendance->refresh(), Text::slotTaken());
        }

        $conversation->delete();
        DB::afterCommit(fn () => $this->presentToAdmin($request->branch_id));

        return new BotReply(Text::submitted($request->load('session')));
    }

    private function offerSlots(Conversation $conversation, ScheduleAttendance $attendance, ?string $intro): BotReply
    {
        $slots = $this->reschedules->canRequest($attendance) ? $this->reschedules->availableSlots($attendance) : [];

        if ($slots === []) {
            $conversation->delete();

            return new BotReply(Text::noSlots(), true, FlowEngine::HANDOVER_MINUTES);
        }

        $list = Text::slotList($attendance->session->teacher?->name ?? 'pengajar', $slots);

        return $this->next($conversation, Conversation::STEP_SLOT, ['slots' => $slots], $intro ? $intro."\n\n".$list : $list);
    }

    // ------------------------------------------------------------------ Admin

    private function admin(Conversation $conversation, WaChat $chat, string $text, string $raw): ?BotReply
    {
        $branch = UserBranch::with('user')->find($conversation->branch_id);
        $owner = $branch?->user;

        // Nomor owner bisa berganti: percakapan lama tidak berlaku lagi.
        if (! $owner || RescheduleService::adminPhone($branch) !== $conversation->phone) {
            $conversation->delete();

            return null;
        }

        // Menu admin (tahap 3): "menu" dari langkah mana pun membuka menu; langkah menu ditangani AdminMenu.
        if (in_array($text, Text::MENU_KEYWORDS, true)) {
            return $this->menu->open($branch, $conversation->phone, $owner);
        }

        if (str_starts_with($conversation->step, AdminMenu::PREFIX)) {
            return $this->menu->handle($conversation, $text, $raw, $branch, $owner, function () use ($conversation) {
                $conversation->delete();

                return new BotReply($this->nextForAdmin($conversation->branch_id, $conversation->phone) ?? Text::adminNothingWaiting());
            });
        }

        $request = ScheduleRescheduleRequest::where('branch_id', $conversation->branch_id)->find($conversation->context('request_id'));

        if (! $request?->isPending()) {
            $conversation->delete();

            return $this->withNext($conversation, Text::adminAlreadyProcessed());
        }

        if ($conversation->step === Conversation::STEP_REJECT_REASON) {
            $reason = trim(preg_replace('/\s+/u', ' ', $raw));

            if (mb_strlen($reason) < 3) {
                return $this->invalid($conversation, Text::askRejectReason(), bare: true);
            }

            $conversation->delete();

            try {
                $this->reschedules->reject($request, $reason, $owner, 'whatsapp');
            } catch (RuntimeException) {
                return $this->withNext($conversation, Text::adminAlreadyProcessed());
            }

            return $this->withNext($conversation, Text::adminRejected($request->load('student')));
        }

        return match ($this->intent->choice($chat, $text, 2, false, '')) {
            1 => $this->approveByAdmin($conversation, $request, $owner),
            2 => $this->next($conversation, Conversation::STEP_REJECT_REASON, [], Text::askRejectReason(), self::ADMIN_IDLE_HOURS * 60),
            default => $this->invalid($conversation, 'Balas *1* untuk setujui, *2* untuk tolak.'),
        };
    }

    private function approveByAdmin(Conversation $conversation, ScheduleRescheduleRequest $request, User $owner): BotReply
    {
        $conversation->delete();

        try {
            $this->reschedules->approve($request, $owner, 'whatsapp');
        } catch (RuntimeException $e) {
            // Tetap menunggu; tidak langsung menyodorkan pengajuan berikutnya supaya yang ini tidak tertimbun.
            return new BotReply(Text::adminApproveFailed($e->getMessage()));
        }

        return $this->withNext($conversation, Text::adminApproved($request->refresh()->load('student')));
    }

    /** Owner mengetik "pengajuan" tanpa percakapan aktif: kirimkan yang paling lama menunggu. */
    private function adminShortcut(string $branchId, string $phone, string $text): ?BotReply
    {
        $menu = in_array($text, Text::MENU_KEYWORDS, true);

        if (! $menu && ! in_array($text, self::ADMIN_KEYWORDS, true)) {
            return null;
        }

        $branch = UserBranch::with('user')->find($branchId);

        if (! $branch?->user || RescheduleService::adminPhone($branch) !== $phone) {
            return null;
        }

        if ($menu) {
            return $this->menu->open($branch, $phone, $branch->user);
        }

        return new BotReply($this->nextForAdmin($branchId, $phone) ?? Text::adminNothingWaiting());
    }

    /** Buka percakapan admin untuk pengajuan tertua yang menunggu; null kalau tidak ada. */
    private function nextForAdmin(string $branchId, string $phone): ?string
    {
        $pending = ScheduleRescheduleRequest::where('branch_id', $branchId)->where('status', ScheduleRescheduleRequest::STATUS_PENDING);
        $request = (clone $pending)->with(['student', 'session.grade.category.subject'])->oldest()->first();

        if (! $request) {
            return null;
        }

        $this->start($branchId, $phone, Conversation::ROLE_ADMIN, Conversation::STEP_REVIEW, ['request_id' => $request->getKey()], now()->addHours(self::ADMIN_IDLE_HOURS));

        return Text::adminReview($request, $pending->count());
    }

    private function withNext(Conversation $conversation, string $text): BotReply
    {
        $next = $this->nextForAdmin($conversation->branch_id, $conversation->phone);

        return new BotReply($next ? $text."\n\n—\n\n".$next : $text);
    }
}

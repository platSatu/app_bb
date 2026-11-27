<?php

namespace App\Services\WhatsApp\Schedule;

use App\Jobs\BillScheduleStudents;
use App\Models\Invoice;
use App\Models\SchedulePortalLink;
use App\Models\SchedulePortalRequest;
use App\Models\ScheduleRescheduleRequest;
use App\Models\ScheduleSession;
use App\Models\ScheduleStudent;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\WaScheduleConversation as Conversation;
use App\Services\Schedule\RescheduleService;
use App\Services\Schedule\ScheduleBillingService;
use App\Services\Schedule\ScheduleReminderService;
use App\Services\Schedule\SchedulePortalService;
use App\Services\WhatsApp\Bot\BotReply;
use App\Support\ScheduleBotText as Text;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/**
 * Menu admin chatbot WhatsApp Jadwal (tahap 3). Hanya untuk nomor owner branch, dibuka dengan mengetik "menu".
 * Isinya fitur yang sudah ada di website, dipanggil lewat service yang sama:
 *  1 rekap absensi · 2 pengajuan ganti hari · 3 link pendaftaran & pendaftar · 4 tagihan les (Buat Tagihan menu Murid)
 *  5 kirim pengingat les besok · 6 kirim rekap mengajar besok.
 * Aksi besar (setujui semua, tagih semua murid, kirim massal) wajib PIN admin; PIN berlaku PIN_MINUTES menit,
 * salah PIN_ATTEMPTS kali = dikunci sementara. Pemeriksaan nomor owner dilakukan ScheduleBot sebelum masuk ke sini.
 */
class AdminMenu
{
    use ConversationSteps;

    public const PREFIX = 'm_';

    private const IDLE_MINUTES = 30;

    private const PIN_MINUTES = 15;

    private const PIN_ATTEMPTS = 5;

    private const PIN_LOCK_SECONDS = 900;

    private const MENU = 'm_menu';
    private const DONE = 'm_done';
    private const REKAP = 'm_rekap';
    private const REKAP_DATE = 'm_rekap_date';
    private const REQUESTS = 'm_requests';
    private const SIGNUP = 'm_signup';
    private const SIGNUP_PERIOD = 'm_signup_period';
    private const BILL = 'm_bill';
    private const BILL_WHO = 'm_bill_who';
    private const BILL_SEARCH = 'm_bill_search';
    private const BILL_PICK = 'm_bill_pick';
    private const BILL_CONFIRM = 'm_bill_confirm';
    private const BLAST = 'm_blast';
    private const PIN = 'm_pin';

    public function __construct(private readonly RescheduleService $reschedules)
    {
    }

    public function open(UserBranch $branch, string $phone, User $owner): BotReply
    {
        $this->start($branch->getKey(), $phone, Conversation::ROLE_ADMIN, self::MENU, [], now()->addMinutes(self::IDLE_MINUTES));

        return new BotReply($this->menuText($branch, $owner));
    }

    /** @param callable(): BotReply $review pengajuan satu per satu (alur setujui / tolak milik ScheduleBot) */
    public function handle(Conversation $conversation, string $text, string $raw, UserBranch $branch, User $owner, callable $review): ?BotReply
    {
        if ($text === '0') {
            return $this->next($conversation, self::MENU, ['pin_action' => null], $this->menuText($branch, $owner), self::IDLE_MINUTES);
        }

        $number = $this->number($text);

        try {
            return match ($conversation->step) {
                self::MENU => $this->onMenu($conversation, $number, $branch),
                self::REKAP => $this->onRekap($conversation, $number, $branch),
                self::REKAP_DATE => ($date = $this->parseDate($raw))
                    ? $this->done($conversation, $this->rekap($branch, $date->copy()->startOfDay(), $date->copy()->endOfDay(), $date->translatedFormat('l, d M Y'), true))
                    : $this->invalid($conversation, Text::askDate(), bare: true),
                self::REQUESTS => match ($number) {
                    1 => $this->withPin($conversation, $owner, 'approve_all'),
                    2 => $review(),
                    default => $this->invalid($conversation, 'Balas *1* atau *2* ya.'),
                },
                self::SIGNUP => match ($number) {
                    1 => $this->done($conversation, $this->signupLinks($branch)),
                    2 => $this->next($conversation, self::SIGNUP_PERIOD, [], Text::signupPeriod(), self::IDLE_MINUTES),
                    default => $this->invalid($conversation, 'Balas *1* atau *2* ya.'),
                },
                self::SIGNUP_PERIOD => ($range = $this->range($number)) ? $this->done($conversation, $this->signups($branch, ...$range)) : $this->invalid($conversation, 'Balas *1*, *2*, atau *3* ya.'),
                self::BILL => match ($number) {
                    1 => $this->next($conversation, self::BILL_WHO, [], Text::billWho($this->billable($branch)->count()), self::IDLE_MINUTES),
                    2 => $this->done($conversation, $this->billStatus($branch)),
                    default => $this->invalid($conversation, 'Balas *1* atau *2* ya.'),
                },
                self::BILL_WHO => match ($number) {
                    1 => $this->withPin($conversation, $owner, 'bill_all'),
                    2 => $this->next($conversation, self::BILL_SEARCH, [], Text::askStudentName(), self::IDLE_MINUTES),
                    default => $this->invalid($conversation, 'Balas *1* atau *2* ya.'),
                },
                self::BILL_SEARCH => $this->onBillSearch($conversation, $raw, $branch),
                self::BILL_PICK => $this->onBillPick($conversation, $number, $branch),
                self::BILL_CONFIRM => match ($number) {
                    1 => $this->done($conversation, $this->billOne($branch, $owner, (string) $conversation->context('student_id'))),
                    2 => $this->done($conversation, Text::cancelled()),
                    default => $this->invalid($conversation, 'Balas *1* untuk buat, *2* untuk batal.'),
                },
                self::BLAST => match ($number) {
                    1 => $this->withPin($conversation, $owner, 'blast_'.$conversation->context('who')),
                    2 => $this->done($conversation, Text::cancelled()),
                    default => $this->invalid($conversation, 'Balas *1* untuk kirim, *2* untuk batal.'),
                },
                self::PIN => $this->onPin($conversation, $raw, $branch, $owner),
                // Selesai satu aksi: hanya "0" (menu) yang ditunggu; pesan lain = chat biasa.
                default => $this->finish($conversation, null),
            };
        } catch (RuntimeException $e) {
            return $this->done($conversation, $e->getMessage());
        }
    }

    // ------------------------------------------------------------------ Menu

    private function onMenu(Conversation $conversation, int $number, UserBranch $branch): ?BotReply
    {
        $waiting = $this->waiting($branch);

        return match ($number) {
            1 => $this->next($conversation, self::REKAP, [], Text::rekapPeriod(), self::IDLE_MINUTES),
            2 => $waiting ? $this->next($conversation, self::REQUESTS, [], Text::requestsMenu($waiting), self::IDLE_MINUTES) : $this->done($conversation, Text::adminNothingWaiting().Text::backHint()),
            3 => $this->next($conversation, self::SIGNUP, [], Text::signupMenu(), self::IDLE_MINUTES),
            4 => $this->next($conversation, self::BILL, [], Text::billMenu(now()->translatedFormat('F Y')), self::IDLE_MINUTES),
            5, 6 => $this->confirmBlast($conversation, $branch, $number === 5 ? 'students' : 'teachers'),
            default => $this->invalid($conversation, 'Balas angka 1 sampai 6 ya.'),
        };
    }

    private function menuText(UserBranch $branch, User $owner): string
    {
        return Text::adminMenu($owner->name, $branch->name, $this->waiting($branch));
    }

    private function waiting(UserBranch $branch): int
    {
        return ScheduleRescheduleRequest::where('branch_id', $branch->getKey())->where('status', ScheduleRescheduleRequest::STATUS_PENDING)->count();
    }

    // ------------------------------------------------------------------ 1. Rekap absensi

    private function onRekap(Conversation $conversation, int $number, UserBranch $branch): ?BotReply
    {
        if ($number === 4) {
            return $this->next($conversation, self::REKAP_DATE, [], Text::askDate(), self::IDLE_MINUTES);
        }

        $range = $this->range($number);

        return $range ? $this->done($conversation, $this->rekap($branch, ...[...$range, $number === 1])) : $this->invalid($conversation, 'Balas angka 1 sampai 4 ya.');
    }

    private function rekap(UserBranch $branch, CarbonInterface $from, CarbonInterface $to, string $label, bool $detail): string
    {
        $sessions = ScheduleSession::with(['teacher:id,name', 'grade.category.subject', 'attendances:id,session_id,status'])
            ->where('branch_id', $branch->getKey())->whereBetween('start_at', [$from, $to])->orderBy('start_at')->get();
        $live = $sessions->where('status', '!=', ScheduleSession::STATUS_CANCELLED);

        $lines = $detail ? $live->take(10)->map(fn (ScheduleSession $s) => '• '.$s->start_at->format('H.i').' '.$s->grade?->label().' · '.($s->teacher?->name ?? '-').' · '
            .$s->attendances->where('status', 'present')->count().'/'.$s->attendances->count().' hadir'
            .($s->status === ScheduleSession::STATUS_SCHEDULED && $s->end_at->isPast() ? ' ⚠️' : ''))->values()->all() : [];

        if ($detail && $live->count() > 10) {
            $lines[] = '… dan '.($live->count() - 10).' sesi lain';
        }

        return Text::rekap($label, [
            'done' => $sessions->where('status', ScheduleSession::STATUS_DONE)->count(),
            'scheduled' => $sessions->where('status', ScheduleSession::STATUS_SCHEDULED)->count(),
            'cancelled' => $sessions->where('status', ScheduleSession::STATUS_CANCELLED)->count(),
        ], $live->flatMap->attendances->countBy('status')->all(),
            $live->where('status', ScheduleSession::STATUS_SCHEDULED)->filter(fn ($s) => $s->end_at->isPast())->count(),
            $lines, route('schedules.sessions.index', ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString()]));
    }

    // ------------------------------------------------------------------ 2. Pengajuan ganti hari

    private function approveAll(UserBranch $branch, User $owner): string
    {
        $ok = [];
        $failed = [];

        ScheduleRescheduleRequest::with('student:id,name')->where('branch_id', $branch->getKey())
            ->where('status', ScheduleRescheduleRequest::STATUS_PENDING)->oldest()->limit(100)->get()
            ->each(function (ScheduleRescheduleRequest $request) use ($owner, &$ok, &$failed) {
                try {
                    $this->reschedules->approve($request, $owner, 'whatsapp');
                    $ok[] = $request->student?->name;
                } catch (RuntimeException $e) {
                    $failed[] = '• '.$request->student?->name.': '.$e->getMessage();
                }
            });

        return Text::approvedAll($ok, $failed);
    }

    // ------------------------------------------------------------------ 3. Pendaftaran

    private function signupLinks(UserBranch $branch): string
    {
        $links = app(SchedulePortalService::class)->linksFor(UserBranch::whereKey($branch->getKey())->get())->get($branch->getKey(), collect());

        return Text::signupLinks($branch->name, $links->sortBy(fn ($l) => $l->type === SchedulePortalLink::TYPE_STUDENT ? 0 : 1)
            ->map(fn (SchedulePortalLink $l) => ['label' => 'Daftar '.$l->typeLabel(), 'url' => $l->url(), 'active' => (bool) $l->is_active])->values()->all());
    }

    private function signups(UserBranch $branch, CarbonInterface $from, CarbonInterface $to, string $label): string
    {
        $items = SchedulePortalRequest::where('branch_id', $branch->getKey())->whereBetween('created_at', [$from, $to])->latest()->get();

        return Text::signups($label, $items->countBy('status')->all(),
            $items->take(10)->map(fn (SchedulePortalRequest $r) => '• '.$r->name.' ('.$r->typeLabel().') · '.$r->statusLabel().' · '.$r->created_at->translatedFormat('d M'))->values()->all()
                + ($items->count() > 10 ? [10 => '… dan '.($items->count() - 10).' lainnya'] : []),
            route('schedules.portal.requests'));
    }

    // ------------------------------------------------------------------ 4. Tagihan

    /** Murid aktif yang bisa ditagih (punya WhatsApp atau email), sama dengan syarat Buat Tagihan. */
    private function billable(UserBranch $branch)
    {
        return ScheduleStudent::where('branch_id', $branch->getKey())->where('status', ScheduleStudent::STATUS_ACTIVE)
            ->where(fn ($q) => $q->whereNotNull('phone')->orWhereNotNull('email'));
    }

    private function onBillSearch(Conversation $conversation, string $raw, UserBranch $branch): ?BotReply
    {
        $name = trim(preg_replace('/\s+/u', ' ', $raw));

        if (mb_strlen($name) < 3) {
            return $this->invalid($conversation, Text::askStudentName(), bare: true);
        }

        $found = $this->billable($branch)->where('name', 'like', '%'.addcslashes($name, '%_\\').'%')->orderBy('name')->limit(9)->get(['id', 'name']);

        if ($found->isEmpty()) {
            return $this->invalid($conversation, "Murid bernama \"{$name}\" tidak ditemukan. Coba tulis nama lain ya.", bare: true);
        }

        return $this->next($conversation, self::BILL_PICK, ['candidates' => $found->modelKeys()], Text::pickStudent($found->pluck('name')->all()), self::IDLE_MINUTES);
    }

    private function onBillPick(Conversation $conversation, int $number, UserBranch $branch): ?BotReply
    {
        $id = ((array) $conversation->context('candidates', []))[$number - 1] ?? null;
        $student = $id ? $this->billable($branch)->find($id) : null;

        if (! $student) {
            return $this->invalid($conversation, 'Balas nomor muridnya ya.');
        }

        return $this->next($conversation, self::BILL_CONFIRM, ['student_id' => $student->getKey()], Text::confirmBill($student->name, now()->translatedFormat('F Y')), self::IDLE_MINUTES);
    }

    private function billOne(UserBranch $branch, User $owner, string $studentId): string
    {
        $student = $this->billable($branch)->findOrFail($studentId);

        return Text::billResult(app(ScheduleBillingService::class)->bill(collect([$student]), now()->startOfMonth(), $owner), now()).Text::backHint();
    }

    private function billStatus(UserBranch $branch): string
    {
        $invoices = Invoice::where('branch_id', $branch->getKey())->where('source', Invoice::SOURCE_SCHEDULE)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->get(['id', 'status', 'amount', 'customer_name', 'due_date']);
        $unpaid = $invoices->where('status', Invoice::STATUS_UNPAID)->sortBy('due_date');

        return Text::billStatus(now()->translatedFormat('F Y'), $invoices->countBy('status')->all(), (int) $unpaid->sum('amount'),
            $unpaid->take(10)->map(fn (Invoice $i) => '• '.$i->customer_name.' · Rp '.number_format((int) $i->amount, 0, ',', '.')
                .($i->due_date ? ' · jatuh tempo '.$i->due_date->translatedFormat('d M') : ''))->values()->all(),
            route('invoices.index'));
    }

    // ------------------------------------------------------------------ 5 & 6. Kirim pengingat massal

    private function confirmBlast(Conversation $conversation, UserBranch $branch, string $who): ?BotReply
    {
        $audience = app(ScheduleReminderService::class)->audience($branch, $this->tomorrow());
        $label = $who === 'students' ? 'murid / orang tua' : 'pengajar';
        $switch = ! empty($branch->wa_notify[$who === 'students' ? 'schedule' : 'teacher_recap']);
        $text = Text::confirmBlast($label, $audience[$who], $switch);

        return $switch && $audience[$who] > 0
            ? $this->next($conversation, self::BLAST, ['who' => $who], $text, self::IDLE_MINUTES)
            : $this->done($conversation, $text);
    }

    // ------------------------------------------------------------------ PIN

    /** Aksi besar: minta PIN dulu, kecuali PIN sudah dimasukkan dengan benar dalam PIN_MINUTES menit terakhir. */
    private function withPin(Conversation $conversation, User $owner, string $action): ?BotReply
    {
        $until = $conversation->context('pin_until');

        if ($until && Carbon::parse($until)->isFuture()) {
            return $this->done($conversation, $this->run($action, $conversation, $owner));
        }

        if (! $owner->wa_pin) {
            return $this->done($conversation, Text::noPin());
        }

        return $this->next($conversation, self::PIN, ['pin_action' => $action], Text::askPin(), self::IDLE_MINUTES);
    }

    private function onPin(Conversation $conversation, string $raw, UserBranch $branch, User $owner): ?BotReply
    {
        $key = 'wa-admin-pin:'.$owner->getKey();

        if (RateLimiter::tooManyAttempts($key, self::PIN_ATTEMPTS)) {
            return $this->done($conversation, Text::pinLocked((int) ceil(RateLimiter::availableIn($key) / 60)));
        }

        $pin = preg_replace('/\D/', '', $raw);

        if (! $owner->wa_pin || ! preg_match('/^\d{4,6}$/', $pin) || ! Hash::check($pin, $owner->wa_pin)) {
            RateLimiter::hit($key, self::PIN_LOCK_SECONDS);
            $left = self::PIN_ATTEMPTS - RateLimiter::attempts($key);

            return $left > 0 ? new BotReply(Text::wrongPin($left)) : $this->done($conversation, Text::pinLocked((int) ceil(self::PIN_LOCK_SECONDS / 60)));
        }

        RateLimiter::clear($key);
        $conversation->forceFill(['context' => [...($conversation->context ?? []), 'pin_until' => now()->addMinutes(self::PIN_MINUTES)->toIso8601String()]])->save();

        return $this->done($conversation, $this->run((string) $conversation->context('pin_action'), $conversation, $owner));
    }

    /** Aksi yang sudah lolos PIN. Branch dibaca ulang dari percakapan (bukan dari isi pesan). */
    private function run(string $action, Conversation $conversation, User $owner): string
    {
        $branch = UserBranch::findOrFail($conversation->branch_id);
        $reminders = app(ScheduleReminderService::class);

        return match ($action) {
            'approve_all' => $this->approveAll($branch, $owner),
            'bill_all' => $this->billAll($branch, $owner, $conversation->phone),
            'blast_students' => Text::blastSent('murid / orang tua', $reminders->students($branch, $this->tomorrow(), false)),
            'blast_teachers' => Text::blastSent('pengajar', $reminders->teachers($branch, $this->tomorrow())),
            default => Text::cancelled(),
        };
    }

    private function billAll(UserBranch $branch, User $owner, string $phone): string
    {
        $count = $this->billable($branch)->count();
        dispatch(new BillScheduleStudents($branch->getKey(), $owner->getKey(), now()->format('Y-m'), $phone));

        return Text::billQueued($count, now()->translatedFormat('F Y'));
    }

    // ------------------------------------------------------------------ Bantuan

    /** Selesai satu aksi: tetap di menu (balas 0 untuk kembali), pesan lain diteruskan ke alur biasa. */
    private function done(Conversation $conversation, string $text): BotReply
    {
        return $this->next($conversation, self::DONE, ['pin_action' => null], $text, self::IDLE_MINUTES);
    }

    /** 1 hari ini · 2 minggu ini · 3 bulan ini -> [dari, sampai, label]; selain itu null. */
    private function range(int $number): ?array
    {
        return match ($number) {
            1 => [today(), today()->endOfDay(), 'hari ini ('.today()->translatedFormat('l, d M').')'],
            2 => [today()->startOfWeek(), today()->endOfWeek(), 'minggu ini ('.today()->startOfWeek()->translatedFormat('d M').' – '.today()->endOfWeek()->translatedFormat('d M').')'],
            3 => [today()->startOfMonth(), today()->endOfMonth(), 'bulan '.today()->translatedFormat('F Y')],
            default => null,
        };
    }

    private function tomorrow(): array
    {
        return [today()->addDay(), today()->addDay()->endOfDay()];
    }

    /** "28/11", "28-11-2026", "28.11.26" -> tanggal; tanggal tidak sah = null. */
    private function parseDate(string $text): ?Carbon
    {
        if (! preg_match('/^\s*(\d{1,2})[\/\-.](\d{1,2})(?:[\/\-.](\d{2}|\d{4}))?\s*$/', $text, $m)) {
            return null;
        }

        $year = isset($m[3]) ? (strlen($m[3]) === 2 ? 2000 + (int) $m[3] : (int) $m[3]) : today()->year;

        return checkdate((int) $m[2], (int) $m[1], $year) ? Carbon::create($year, (int) $m[2], (int) $m[1]) : null;
    }
}

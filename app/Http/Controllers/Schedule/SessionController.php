<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Schedule\Concerns\InScheduleArea;
use App\Models\ScheduleAttendance;
use App\Models\ScheduleRoom;
use App\Models\ScheduleRoutine;
use App\Models\ScheduleSession;
use App\Models\ScheduleTeacher;
use App\Models\UserBranch;
use App\Services\Schedule\ScheduleSessionService;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Sesi per tanggal: daftar dengan filter tanggal, absensi, pindah, batal, dan sesi pengganti. */
class SessionController extends Controller
{
    use InScheduleArea;

    private const GRADE_WITH = ['grade:id,name,category_id,price,meetings_per_month,teacher_percent', 'grade.category:id,name,subject_id', 'grade.category.subject:id,name'];

    public function __construct(private readonly ScheduleSessionService $sessions)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $this->isAdmin($request) && ! UserBranch::where('user_id', $user->getKey())->exists()) {
            return $this->branchRequired();
        }

        // Tanpa filter tanggal: tampilkan minggu ini.
        $from = $request->date('date_from') ?? ($request->hasAny(['date_from', 'date_to']) ? null : today()->startOfWeek());
        $to = $request->date('date_to') ?? ($request->hasAny(['date_from', 'date_to']) ? null : today()->endOfWeek());

        $sessions = ScheduleSession::accessibleBy($user)
            ->with(['branch:id,name,company_id', 'branch.company:id,name', 'teacher:id,name', 'room:id,name', 'attendances.student:id,name', ...self::GRADE_WITH])
            ->when($from, fn ($q) => $q->where('start_at', '>=', $from->copy()->startOfDay()))
            ->when($to, fn ($q) => $q->where('start_at', '<=', $to->copy()->endOfDay()))
            ->when($request->query('teacher'), fn ($q, $id) => $q->where('teacher_id', $id))
            ->when($request->query('room'), fn ($q, $id) => $q->where('room_id', $id))
            ->when($request->query('routine'), fn ($q, $id) => $q->where('routine_id', $id))
            ->when($request->query('student'), fn ($q, $id) => $q->whereHas('attendances', fn ($a) => $a->where('student_id', $id)))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('start_at')
            ->paginate(50)
            ->withQueryString();

        $branchScope = fn ($query) => $query->accessibleBy($user)->when($this->isAdmin($request), fn ($q) => $q->whereRaw('1 = 0'));

        return view('schedule.sessions.index', [
            ...$this->shared($request),
            'items' => $sessions,
            'from' => $from,
            'to' => $to,
            'teachers' => $branchScope(ScheduleTeacher::query())->orderBy('name')->get(['id', 'name']),
            'rooms' => $branchScope(ScheduleRoom::query())->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function show(Request $request, string $session): View
    {
        $model = ScheduleSession::accessibleBy($request->user())
            ->with(['teacher:id,name', 'room:id,name', 'routine', 'attendances.student:id,name,phone', 'attendances.makeupSession', 'attendances.replaces.session', ...self::GRADE_WITH])
            ->findOrFail($session);

        return view('schedule.sessions.show', [
            ...$this->shared($request),
            'session' => $model,
            'teachers' => ScheduleTeacher::where('branch_id', $model->branch_id)->active()->whereHas('grades', fn ($q) => $q->whereKey($model->grade_id))->orderBy('name')->get(['id', 'name']),
            'rooms' => ScheduleRoom::where('branch_id', $model->branch_id)->active()->orderBy('name')->get(['id', 'name']),
            'openings' => $this->openings($model),
        ]);
    }

    /**
     * Jam yang bisa dipakai untuk memindahkan pertemuan ini, dalam minggu yang sama.
     * Jamnya diambil dari jam buka ruangan, lalu ditandai terpakai kalau pengajar, ruangan,
     * atau muridnya sudah punya pertemuan lain yang beririsan. Dipilih lewat centang, tidak diketik,
     * supaya tidak mungkin memasukkan jam yang bentrok.
     */
    private function openings(ScheduleSession $session): array
    {
        $from = $session->date->copy()->startOfWeek();
        $until = $session->date->copy()->endOfWeek();
        $minutes = max(1, (int) $session->start_at->diffInMinutes($session->end_at));

        $times = ScheduleRoutine::where('room_id', $session->room_id)
            ->where('status', ScheduleRoutine::STATUS_ACTIVE)
            ->pluck('start_time')
            ->push($session->start_at->format('H:i:s'))
            ->map(fn ($time) => substr((string) $time, 0, 5))
            ->unique()->sort()->values();

        $studentIds = $session->attendances->pluck('student_id')->all();

        $busy = ScheduleSession::where('branch_id', $session->branch_id)
            ->whereBetween('date', [$from->toDateString(), $until->toDateString()])
            ->where('status', '!=', ScheduleSession::STATUS_CANCELLED)
            ->whereKeyNot($session->getKey())
            ->where(fn ($q) => $q->where('room_id', $session->room_id)
                ->orWhere('teacher_id', $session->teacher_id)
                ->orWhereHas('attendances', fn ($a) => $a->whereIn('student_id', $studentIds ?: ['-'])))
            ->get(['id', 'start_at', 'end_at']);

        $days = [];

        for ($day = $from->copy(); $day->lte($until); $day->addDay()) {
            $cells = [];

            foreach ($times as $time) {
                $start = $day->copy()->setTimeFromTimeString($time);
                $end = $start->copy()->addMinutes($minutes);

                $cells[$time] = [
                    'date' => $day->toDateString(),
                    'time' => $time,
                    'taken' => $busy->contains(fn ($other) => $other->start_at->lt($end) && $other->end_at->gt($start)),
                    'past' => $start->lte(now()),
                    'current' => $session->date->isSameDay($day) && $session->start_at->format('H:i') === $time,
                ];
            }

            $days[] = ['label' => $day->translatedFormat('D'), 'date' => $day->copy(), 'cells' => $cells];
        }

        return ['times' => $times->all(), 'days' => $days];
    }

    public function attendance(Request $request, string $session): RedirectResponse
    {
        $model = $this->find($request, $session);
        $data = $request->validate([
            'statuses' => ['required', 'array'],
            'statuses.*' => ['required', Rule::in([ScheduleAttendance::STATUS_PRESENT, ScheduleAttendance::STATUS_ABSENT, ScheduleAttendance::STATUS_EXCUSED])],
            'notes' => ['nullable', 'array'],
            'notes.*' => ['nullable', 'string', 'max:255'],
        ], ['statuses.required' => 'Isi status kehadiran murid dulu ya.']);

        return $this->run(fn () => $this->sessions->complete($model, $data['statuses'], $data['notes'] ?? null, $request->user()),
            'Absensi tersimpan dan sesi ditandai selesai. Honor pengajar sudah tercatat untuk laporan.');
    }

    public function cancel(Request $request, string $session): RedirectResponse
    {
        $model = $this->find($request, $session);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:500']], [], ['reason' => 'alasan'])['reason'];

        return $this->run(fn () => $this->sessions->cancel($model, $reason), 'Sesi dibatalkan. Sesi ini tidak dihitung honor.');
    }

    public function move(Request $request, string $session): RedirectResponse
    {
        $model = $this->find($request, $session);
        $data = $this->slotRules($request, $model->branch_id);

        return $this->run(fn () => $this->sessions->move($model, $data['date'], $data['time'], $data['teacher_id'], $data['room_id']),
            'Sesi berhasil dipindah ke '.$data['date'].' jam '.$data['time'].'.');
    }

    public function makeup(Request $request, string $session, string $attendance): RedirectResponse
    {
        $model = $this->find($request, $session);
        $record = $model->attendances()->findOrFail($attendance);
        $data = $this->slotRules($request, $model->branch_id);

        try {
            $makeup = $this->sessions->makeup($record, $data['date'], $data['time'], $data['teacher_id'], $data['room_id']);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route($this->routes($request).'.sessions.show', $makeup)->with('success', 'Sesi pengganti berhasil dibuat.');
    }

    private function slotRules(Request $request, string $branchId): array
    {
        $inBranch = fn (string $table) => Rule::exists($table, 'id')->where('branch_id', $branchId)->where('status', 'active');

        return $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'teacher_id' => ['required', 'string', $inBranch('schedule_teachers')],
            'room_id' => ['required', 'string', $inBranch('schedule_rooms')],
        ], [], ['date' => 'tanggal', 'time' => 'jam', 'teacher_id' => 'pengajar', 'room_id' => 'ruangan']);
    }

    private function run(callable $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', $message);
    }

    private function shared(Request $request): array
    {
        return ['resource' => 'sessions', 'routes' => $this->routes($request), 'isAdmin' => $this->isAdmin($request), 'canManage' => $this->canManage($request)];
    }

    private function find(Request $request, string $id): ScheduleSession
    {
        return ScheduleSession::accessibleBy($request->user(), Menu::ABILITY_MANAGE)->findOrFail($id);
    }
}

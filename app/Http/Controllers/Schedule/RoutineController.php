<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Schedule\Concerns\InScheduleArea;
use App\Models\ScheduleGrade;
use App\Models\ScheduleRoom;
use App\Models\ScheduleRoutine;
use App\Models\ScheduleSession;
use App\Models\ScheduleStudent;
use App\Models\ScheduleTeacher;
use App\Models\UserBranch;
use App\Services\Schedule\ScheduleRoutineService;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/** Jadwal rutin mingguan. Branch selalu mengikuti grade yang dipilih. */
class RoutineController extends Controller
{
    use InScheduleArea;

    public function __construct(private readonly ScheduleRoutineService $routines)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $this->isAdmin($request) && ! UserBranch::where('user_id', $user->getKey())->exists()) {
            return $this->branchRequired();
        }

        $search = trim((string) $request->query('search'));

        $routines = ScheduleRoutine::accessibleBy($user)
            ->with(['branch:id,name,company_id', 'branch.company:id,name', 'teacher:id,name', 'room:id,name', 'students:id,name', ...ScheduleRoutine::LABEL_WITH])
            ->withCount(['sessions as upcoming_count' => fn ($q) => $q->where('status', ScheduleSession::STATUS_SCHEDULED)->where('start_at', '>', now())])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('teacher', fn ($t) => $t->where('name', 'like', "%{$search}%"))
                ->orWhereHas('students', fn ($s) => $s->where('name', 'like', "%{$search}%"))
                ->orWhereHas('grade', fn ($g) => $g->where('name', 'like', "%{$search}%"))))
            ->when($request->query('teacher'), fn ($q, $id) => $q->where('teacher_id', $id))
            ->when($request->query('student'), fn ($q, $id) => $q->whereHas('students', fn ($s) => $s->whereKey($id)))
            ->when($request->query('grade'), fn ($q, $id) => $q->where('grade_id', $id))
            ->when($request->query('day'), fn ($q, $day) => $q->where('day_of_week', $day))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->paginate(20)
            ->withQueryString();

        return view('schedule.routines.index', [...$this->shared($request), 'items' => $routines]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $grade = $request->query('grade')
            ? ScheduleGrade::accessibleBy($request->user(), Menu::ABILITY_MANAGE)->findOrFail($request->query('grade'))
            : null;

        if (! $grade && $this->isAdmin($request)) {
            return redirect()->route('superadmin.schedules.grades.index')->with('info', 'Pilih grade dulu, lalu klik tombol + Jadwal di barisnya.');
        }

        $routine = new ScheduleRoutine([
            'grade_id' => $grade?->getKey(),
            'room_id' => $grade?->category?->subject?->room_id,
            'duration_minutes' => $grade?->duration_minutes ?? 60,
            'capacity' => 1,
            'starts_on' => today(),
            'status' => ScheduleRoutine::STATUS_ACTIVE,
        ]);

        $options = $this->options($request, $grade?->branch_id);

        if ($options['grades']->isEmpty()) {
            return redirect()->route($this->routes($request).'.grades.index')->with('info', 'Buat grade dulu ya (Ruangan › Bidang › Kategori › Grade), setelah itu jadwal bisa dibuat.');
        }

        return $this->form($request, $routine, $options);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new ScheduleRoutine(), 'Jadwal berhasil dibuat dan sesinya sudah disiapkan sampai akhir bulan depan.');
    }

    public function edit(Request $request, string $routine): View
    {
        $model = $this->find($request, $routine);

        return $this->form($request, $model, $this->options($request, $model->branch_id));
    }

    public function update(Request $request, string $routine): RedirectResponse
    {
        return $this->save($request, $this->find($request, $routine), 'Jadwal berhasil diperbarui. Sesi mendatang yang belum diabsen ikut disesuaikan.');
    }

    public function destroy(Request $request, string $routine): RedirectResponse
    {
        try {
            $this->routines->delete($this->find($request, $routine));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Jadwal beserta sesi yang belum berjalan sudah dihapus.');
    }

    private function save(Request $request, ScheduleRoutine $routine, string $message): RedirectResponse
    {
        $grade = ScheduleGrade::accessibleBy($request->user(), Menu::ABILITY_MANAGE)
            ->when($routine->exists, fn ($q) => $q->where('branch_id', $routine->branch_id))
            ->find((string) $request->input('grade_id'));

        if (! $grade) {
            throw ValidationException::withMessages(['grade_id' => 'Pilih grade dari daftar yang tersedia.']);
        }

        $branchId = $grade->branch_id;
        $inBranch = fn (string $table) => Rule::exists($table, 'id')->where('branch_id', $branchId)->where('status', 'active');

        $data = $request->validate([
            'teacher_id' => ['required', 'string', $inBranch('schedule_teachers')],
            'room_id' => ['required', 'string', $inBranch('schedule_rooms')],
            'day_of_week' => ['required', 'integer', Rule::in(array_keys(ScheduleRoutine::DAYS))],
            'start_time' => ['required', 'date_format:H:i'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:600'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['string', 'distinct', $inBranch('schedule_students')],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(array_keys(ScheduleRoutine::STATUS_LABELS))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'teacher_id.exists' => 'Pengajar tidak ditemukan atau sedang tidak aktif.',
            'room_id.exists' => 'Ruangan tidak ditemukan atau sedang tidak aktif.',
            'student_ids.*.exists' => 'Ada murid yang tidak ditemukan atau sedang tidak aktif.',
        ], [
            'teacher_id' => 'pengajar', 'room_id' => 'ruangan', 'day_of_week' => 'hari', 'start_time' => 'jam mulai',
            'duration_minutes' => 'durasi', 'capacity' => 'kapasitas', 'starts_on' => 'mulai berlaku', 'ends_on' => 'berlaku sampai',
        ]);

        if (! $grade->teachers()->whereKey($data['teacher_id'])->exists()) {
            throw ValidationException::withMessages(['teacher_id' => 'Pengajar ini belum ditandai mengajar grade '.$grade->name.'. Centang dulu di menu Pengajar.']);
        }

        $routine->forceFill(['branch_id' => $branchId]);

        try {
            $this->routines->save($routine, ['grade_id' => $grade->getKey(), ...collect($data)->except('student_ids')->all()], $data['student_ids'] ?? []);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route($this->routes($request).'.routines.index')->with('success', $message);
    }

    /** Pilihan form; superadmin hanya melihat data branch jadwal ini. */
    private function options(Request $request, ?string $branchId): array
    {
        $user = $request->user();
        $scoped = fn ($query) => $query->accessibleBy($user)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when(! $branchId && $user->isPlatformTeam(), fn ($q) => $q->whereRaw('1 = 0'));

        return [
            'grades' => $scoped(ScheduleGrade::query())->with(['branch:id,name', 'teachers:id', 'category.subject:id,name,room_id', 'category:id,name,subject_id'])->get()->sortBy(fn ($g) => $g->label())->values(),
            'teachers' => $scoped(ScheduleTeacher::query())->orderBy('name')->get(['id', 'name', 'branch_id', 'status']),
            'rooms' => $scoped(ScheduleRoom::query())->orderBy('name')->get(['id', 'name', 'branch_id', 'status']),
            'students' => $scoped(ScheduleStudent::query())->orderBy('name')->get(['id', 'name', 'phone', 'branch_id', 'status']),
        ];
    }

    private function form(Request $request, ScheduleRoutine $routine, array $options): View
    {
        return view('schedule.routines.form', [
            ...$this->shared($request),
            ...$options,
            'item' => $routine,
            'selectedStudents' => collect(old('student_ids', $routine->exists ? $routine->students()->pluck('schedule_students.id')->all() : [])),
        ]);
    }

    private function shared(Request $request): array
    {
        return ['resource' => 'routines', 'routes' => $this->routes($request), 'isAdmin' => $this->isAdmin($request), 'canManage' => $this->canManage($request)];
    }

    private function find(Request $request, string $id): ScheduleRoutine
    {
        return ScheduleRoutine::accessibleBy($request->user(), Menu::ABILITY_MANAGE)->findOrFail($id);
    }
}

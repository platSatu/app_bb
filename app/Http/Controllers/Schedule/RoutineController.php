<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Schedule\Concerns\InScheduleArea;
use App\Models\ScheduleAttendance;
use App\Models\ScheduleGrade;
use App\Models\ScheduleRoom;
use App\Models\ScheduleRoutine;
use App\Models\ScheduleSession;
use App\Models\ScheduleStudent;
use App\Models\ScheduleTeacher;
use App\Models\UserBranch;
use App\Services\Schedule\ScheduleRoutineService;
use App\Support\Menu;
use App\Support\XlsxExport;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use RuntimeException;

/** Jadwal rutin mingguan per bulan (lanjut bulan berikutnya lewat Duplikat). Branch selalu mengikuti grade yang dipilih. */
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
        $month = $this->month($request);

        $routines = ScheduleRoutine::accessibleBy($user)
            // Jadwal yang berlaku di bulan yang dipilih (jadwal lama tanpa tanggal akhir ikut tampil).
            ->whereDate('starts_on', '<=', $month->copy()->endOfMonth())
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $month))
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

        return view('schedule.routines.index', [...$this->shared($request), 'items' => $routines, 'month' => $month]);
    }

    /** Salin satu jadwal ke bulan berikutnya (murid yang masih aktif ikut). */
    public function duplicate(Request $request, string $routine): RedirectResponse
    {
        try {
            $copy = $this->routines->duplicate($this->find($request, $routine));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Jadwal disalin ke '.$copy->monthLabel().' bersama '.$copy->students()->count().' murid aktif.');
    }

    /** Salin semua jadwal aktif di bulan yang dipilih ke bulan berikutnya. Yang sudah ada / bentrok dilewati & dilaporkan. */
    public function duplicateMonth(Request $request): RedirectResponse
    {
        $month = $this->month($request);
        $done = 0;
        $skipped = [];

        ScheduleRoutine::accessibleBy($request->user(), Menu::ABILITY_MANAGE)->active()->with(ScheduleRoutine::LABEL_WITH)
            ->whereDate('starts_on', '>=', $month)->whereDate('starts_on', '<=', $month->copy()->endOfMonth())
            ->get()->each(function (ScheduleRoutine $routine) use (&$done, &$skipped) {
                try {
                    $this->routines->duplicate($routine);
                    $done++;
                } catch (RuntimeException $e) {
                    $skipped[] = $e->getMessage();
                }
            });

        $next = $month->copy()->addMonthNoOverflow()->translatedFormat('F Y');

        return back()->with($done ? 'success' : 'info', "{$done} jadwal disalin ke {$next}.".($skipped ? ' Dilewati: '.implode(' · ', array_slice($skipped, 0, 5)).(count($skipped) > 5 ? ' …' : '') : ''));
    }

    /**
     * Rekap bulanan per jadwal: peserta (masuk/keluar), kehadiran per tanggal, honor, dan tagihan prorata
     * (harga grade ÷ pertemuan/bulan × sesi yang diikuti, maksimal harga sebulan). ?export=1 = Excel.
     */
    public function recap(Request $request): View|StreamedResponse
    {
        $month = $this->month($request);
        $period = $month->format('Y-m');

        $routines = ScheduleRoutine::accessibleBy($request->user())
            // Grade ditulis lengkap (bukan LABEL_WITH) supaya kolom harga & pertemuan ikut termuat.
            ->with(['teacher:id,name', 'grade:id,name,price,meetings_per_month,category_id', 'grade.category:id,name,subject_id', 'grade.category.subject:id,name',
                'participants' => fn ($q) => $q->orderBy('name'),
                'sessions' => fn ($q) => $q->where('period', $period)->orderBy('start_at')->with('attendances:id,session_id,student_id,status,teacher_fee')])
            ->whereHas('sessions', fn ($q) => $q->where('period', $period))
            ->orderBy('day_of_week')->orderBy('start_time')->get();

        $rows = $routines->map(function (ScheduleRoutine $routine) {
            $sessions = $routine->sessions->values();
            $price = (int) $routine->grade?->price;
            $meetings = max(1, (int) $routine->grade?->meetings_per_month);

            return ['routine' => $routine, 'sessions' => $sessions, 'students' => $routine->participants->map(function ($student) use ($sessions, $price, $meetings) {
                $marks = $sessions->map(fn ($session) => $session->attendances->firstWhere('student_id', $student->id));
                $followed = $sessions->filter(fn ($session, $i) => $marks[$i] && $session->status !== ScheduleSession::STATUS_CANCELLED)->count();

                return [
                    'student' => $student,
                    'joined_on' => $student->pivot->joined_on,
                    'left_on' => $student->pivot->left_on,
                    'marks' => $marks,
                    'present' => $marks->filter(fn ($a) => $a?->status === ScheduleAttendance::STATUS_PRESENT)->count(),
                    'followed' => $followed,
                    'charge' => (int) min($price, round($price / $meetings * $followed)),
                    'teacher_fee' => (int) $marks->sum(fn ($a) => $a?->teacher_fee),
                ];
            })];
        });

        if ($request->boolean('export')) {
            $date = fn ($value, $empty) => $value ? Carbon::parse($value)->format('d/m/Y') : $empty;

            return XlsxExport::download('rekap-jadwal-'.$period, ['Jadwal', 'Pengajar', 'Murid', 'Masuk', 'Keluar', 'Sesi diikuti', 'Hadir', 'Tagihan (prorata)', 'Honor pengajar'],
                $rows->flatMap(fn ($row) => $row['students']->map(fn ($s) => [
                    $row['routine']->label(), $row['routine']->teacher?->name, $s['student']->name, $date($s['joined_on'], 'awal'), $date($s['left_on'], '-'),
                    $s['followed'], $s['present'], $s['charge'], $s['teacher_fee'],
                ])));
        }

        return view('schedule.routines.recap', [...$this->shared($request), 'rows' => $rows, 'month' => $month]);
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
            'starts_on' => $this->month($request),
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
            'month' => ['required', 'date_format:Y-m'],
            'status' => ['required', Rule::in(array_keys(ScheduleRoutine::STATUS_LABELS))],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'teacher_id.exists' => 'Pengajar tidak ditemukan atau sedang tidak aktif.',
            'room_id.exists' => 'Ruangan tidak ditemukan atau sedang tidak aktif.',
            'student_ids.*.exists' => 'Ada murid yang tidak ditemukan atau sedang tidak aktif.',
        ], [
            'teacher_id' => 'pengajar', 'room_id' => 'ruangan', 'day_of_week' => 'hari', 'start_time' => 'jam mulai',
            'duration_minutes' => 'durasi', 'capacity' => 'kapasitas', 'month' => 'bulan',
        ]);

        // Satu jadwal = satu bulan penuh; bulan berikutnya lewat tombol Duplikat.
        $start = Carbon::createFromFormat('!Y-m', $data['month']);
        $data = [...collect($data)->except('month')->all(), 'starts_on' => $start->toDateString(), 'ends_on' => $start->copy()->endOfMonth()->toDateString()];

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

    /** Bulan dari ?month=YYYY-MM (bawaan: bulan ini). */
    private function month(Request $request): Carbon
    {
        $value = (string) $request->input('month');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) ? Carbon::createFromFormat('!Y-m', $value) : today()->startOfMonth();
    }

    private function find(Request $request, string $id): ScheduleRoutine
    {
        return ScheduleRoutine::accessibleBy($request->user(), Menu::ABILITY_MANAGE)->findOrFail($id);
    }
}

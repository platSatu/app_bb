<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\InPortal;
use App\Models\ScheduleAttendance;
use App\Models\SchedulePortalRequest;
use App\Models\ScheduleSession;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Portal murid/orang tua: beranda, jadwal les, riwayat kehadiran, dan profil. */
class PortalController extends Controller
{
    use InPortal;

    public function home(Request $request): View
    {
        $user = $request->user();
        $studentIds = $this->students($user)->modelKeys();
        $teacherIds = $this->teachers($user)->modelKeys();

        return view('portal.home', [
            'requests' => SchedulePortalRequest::with('branch:id,name')->where('user_id', $user->getKey())
                ->where(fn ($q) => $q->where('status', SchedulePortalRequest::STATUS_PENDING)
                    ->orWhere(fn ($r) => $r->where('status', SchedulePortalRequest::STATUS_REJECTED)->where('reviewed_at', '>=', now()->subDays(14))))
                ->latest()->get(),
            'lessons' => $studentIds ? $this->studentSessions(collect($studentIds))->where('status', ScheduleSession::STATUS_SCHEDULED)
                ->where('start_at', '>=', now()->subHour())->orderBy('start_at')->limit(5)->get() : collect(),
            'teaching' => $teacherIds ? ScheduleSession::with([...self::SESSION_WITH, 'attendances.student:id,name'])->whereIn('teacher_id', $teacherIds)
                ->whereBetween('start_at', [today(), today()->endOfDay()])->orderBy('start_at')->get() : collect(),
            'toFill' => $teacherIds ? TeachingController::needsAttendance($teacherIds)->count() : 0,
            'multiChild' => count($studentIds) > 1,
        ]);
    }

    public function schedule(Request $request): View
    {
        $students = $this->students($request->user());
        $tabs = $this->tabs($students, fn ($s) => $s->name.($students->where('name', $s->name)->count() > 1 ? ' · '.$s->branch?->name : ''));
        $active = $this->selected($request, 'anak', $tabs);
        $ids = $active ? collect([$active]) : $students->modelKeys();

        $sessions = $students->isEmpty() ? null : $this->studentSessions(collect($ids))
            ->where('start_at', '>=', today())
            ->orderBy('start_at')
            ->paginate(20)
            ->withQueryString();

        return view('portal.schedule', [
            'sessions' => $sessions,
            'tabs' => $tabs,
            'active' => $active,
            'showStudent' => count($tabs) > 1 && ! $active,
            'colors' => $this->branchColors($students->pluck('branch_id')),
        ]);
    }

    public function attendance(Request $request): View
    {
        $students = $this->students($request->user());
        $tabs = $this->tabs($students, fn ($s) => $s->name.($students->where('name', $s->name)->count() > 1 ? ' · '.$s->branch?->name : ''));
        $active = $this->selected($request, 'anak', $tabs);
        $ids = $active ? [$active] : $students->modelKeys();
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('bulan')) ? Carbon::createFromFormat('Y-m', $request->query('bulan'))->startOfMonth() : null;

        $query = ScheduleAttendance::whereIn('student_id', $ids)
            ->where('status', '!=', ScheduleAttendance::STATUS_PENDING)
            ->whereHas('session', fn ($q) => $q->where('status', ScheduleSession::STATUS_DONE)
                ->when($month, fn ($m) => $m->whereBetween('start_at', [$month, $month->copy()->endOfMonth()])));

        return view('portal.attendance', [
            'items' => $students->isEmpty() ? null : (clone $query)
                ->with(['student:id,name', ...collect(self::SESSION_WITH)->map(fn ($r) => 'session.'.$r)->all()])
                ->orderByDesc(ScheduleSession::select('start_at')->whereColumn('schedule_sessions.id', 'schedule_attendances.session_id'))
                ->paginate(20)
                ->withQueryString(),
            'summary' => (clone $query)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'tabs' => $tabs,
            'active' => $active,
            'month' => $month,
            'showStudent' => count($tabs) > 1 && ! $active,
            'colors' => $this->branchColors($students->pluck('branch_id')),
        ]);
    }

    public function profile(Request $request): View
    {
        $user = $request->user();

        return view('portal.profile', [
            'user' => $user,
            'students' => $this->students($user),
            'teachers' => $this->teachers($user),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->merge(['handphone' => User::normalizePhone($request->input('handphone'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'handphone' => ['required', 'regex:/^62[1-9][0-9]{8,12}$/', Rule::unique(User::class)->ignore($user->getKey())],
        ], [
            'handphone.regex' => 'Nomor WhatsApp belum benar. Isi 9–13 digit tanpa awalan 0, contoh: 81234567890.',
            'handphone.unique' => 'Nomor WhatsApp ini sudah dipakai akun lain.',
        ], ['name' => 'nama', 'handphone' => 'nomor WhatsApp']);

        $user->fill($data)->save();

        return back()->with('success', 'Profil sudah diperbarui.');
    }
}

<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Portal\Concerns\InPortal;
use App\Models\ScheduleAttendance;
use App\Models\ScheduleSession;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Peran murid/orang tua: jadwal les & riwayat kehadiran (per anak). */
class PortalController extends Controller
{
    use InPortal;

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
}

<?php

namespace App\Http\Controllers\Schedule;

use App\Http\Controllers\Controller;
use App\Models\ScheduleRescheduleRequest;
use App\Services\Schedule\RescheduleService;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Schedules › Ganti Hari (owner): pengajuan ganti hari dari orang tua lewat WhatsApp.
 * Bisa disetujui / ditolak di sini atau lewat WhatsApp; keduanya memakai RescheduleService yang sama.
 */
class RescheduleController extends Controller
{
    public function __construct(private readonly RescheduleService $reschedules)
    {
    }

    public function index(Request $request): View
    {
        $status = array_key_exists((string) $request->query('status'), ScheduleRescheduleRequest::STATUS_LABELS)
            ? $request->query('status') : ScheduleRescheduleRequest::STATUS_PENDING;
        $base = ScheduleRescheduleRequest::accessibleBy($request->user());

        return view('schedule.reschedules.index', [
            'resource' => 'reschedules',
            'routes' => 'schedules',
            'status' => $status,
            'counts' => (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'items' => (clone $base)->where('status', $status)
                ->with(['branch:id,name', 'student:id,name,guardian_name,phone', 'slotTeacher:id,name', 'slotRoom:id,name', 'reviewer:id,name',
                    'session:id,grade_id,start_at,end_at,status', 'session.grade.category.subject', 'makeupSession:id,start_at'])
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'showBranch' => (clone $base)->distinct()->count('branch_id') > 1,
        ]);
    }

    public function approve(Request $request, string $reschedule): RedirectResponse
    {
        $item = $this->find($request, $reschedule);

        try {
            $this->reschedules->approve($item, $request->user(), 'web');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $item->mode === ScheduleRescheduleRequest::MODE_MOVE
            ? 'Disetujui. Sesinya sudah dipindah dan orang tua dikabari lewat WhatsApp.'
            : 'Disetujui. Sesi pengganti dibuat otomatis setelah sesi lamanya ditutup, orang tua sudah dikabari.');
    }

    public function reject(Request $request, string $reschedule): RedirectResponse
    {
        $item = $this->find($request, $reschedule);
        $reason = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']], ['reason.required' => 'Tulis alasan singkat ya, alasannya disampaikan ke orang tua.'], ['reason' => 'alasan'])['reason'];

        try {
            $this->reschedules->reject($item, $reason, $request->user(), 'web');
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pengajuan ditolak dan alasannya sudah disampaikan ke orang tua.');
    }

    /** Hanya pengajuan di branch milik owner yang paket Jadwal-nya aktif. */
    private function find(Request $request, string $id): ScheduleRescheduleRequest
    {
        return ScheduleRescheduleRequest::accessibleBy($request->user(), Menu::ABILITY_MANAGE)->findOrFail($id);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\SchedulePortalRequest;
use App\Models\ScheduleRoutine;
use App\Models\ScheduleSession;
use App\Models\UserBranch;
use App\Models\WaChat;
use App\Models\WaDevice;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Dashboard owner: hal yang perlu ditindaklanjuti & jadwal hari ini, semua dibatasi ke branch miliknya.
 * Tim platform (superadmin/staff) menyusul di tahap dashboard superadmin.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        if ($user->isPlatformTeam()) {
            return view('dashboard', ['owner' => false]);
        }

        $branchIds = UserBranch::where('user_id', $user->getKey())->pluck('id');

        return view('dashboard', [
            'owner' => true,
            'hasBranch' => $branchIds->isNotEmpty(),
            'actions' => $branchIds->isEmpty() ? [] : $this->actions($branchIds),
            'today' => $this->today($branchIds),
            'usesSchedule' => $branchIds->isNotEmpty() && ScheduleRoutine::whereIn('branch_id', $branchIds)->exists(),
            'multiBranch' => $branchIds->count() > 1,
        ]);
    }

    /** Hanya yang jumlahnya lebih dari 0 yang ditampilkan. */
    private function actions($branchIds): array
    {
        $today = today();
        $unpaid = Invoice::whereIn('branch_id', $branchIds)->where('status', Invoice::STATUS_UNPAID);
        $overdue = (clone $unpaid)->whereDate('due_date', '<', $today);
        $dueSoon = (clone $unpaid)->whereBetween('due_date', [$today->toDateString(), $today->copy()->addDays(7)->toDateString()]);
        $unread = WaChat::whereIn('branch_id', $branchIds)->where('is_group', false)->where('unread_count', '>', 0);
        $rupiah = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.');

        $items = [
            [
                'count' => SchedulePortalRequest::whereIn('branch_id', $branchIds)->where('status', SchedulePortalRequest::STATUS_PENDING)->count(),
                'label' => 'pendaftar portal menunggu persetujuan',
                'icon' => 'ri-user-received-2-line', 'color' => 'primary',
                'url' => route('schedules.portal.requests'),
            ],
            [
                'count' => ScheduleSession::whereIn('branch_id', $branchIds)->where('status', ScheduleSession::STATUS_SCHEDULED)
                    ->whereBetween('start_at', [now()->subDays(60), now()])->count(),
                'label' => 'sesi sudah lewat tapi belum diabsen',
                'icon' => 'ri-checkbox-multiple-line', 'color' => 'danger',
                'url' => route('schedules.sessions.index', ['status' => ScheduleSession::STATUS_SCHEDULED, 'date_from' => $today->copy()->subDays(60)->toDateString(), 'date_to' => $today->toDateString()]),
            ],
            [
                'count' => $overdue->count(),
                'label' => 'invoice lewat jatuh tempo',
                'note' => fn () => 'Total '.$rupiah((clone $overdue)->sum('amount')),
                'icon' => 'ri-alarm-warning-line', 'color' => 'danger',
                'url' => route('invoices.index', ['status' => Invoice::STATUS_UNPAID]),
            ],
            [
                'count' => $dueSoon->count(),
                'label' => 'invoice jatuh tempo dalam 7 hari',
                'note' => fn () => 'Total '.$rupiah((clone $dueSoon)->sum('amount')),
                'icon' => 'ri-calendar-schedule-line', 'color' => 'warning',
                'url' => route('invoices.index', ['status' => Invoice::STATUS_UNPAID]),
            ],
            [
                'count' => $unread->count(),
                'label' => 'chat WhatsApp belum dibaca',
                'icon' => 'ri-whatsapp-line', 'color' => 'success',
                'url' => ($device = (clone $unread)->orderByDesc('last_message_at')->value('device_id'))
                    ? route('whatsapp.inbox.show', $device) : route('whatsapp.devices.index'),
            ],
            [
                // Nomor yang pernah ditautkan tapi sekarang tidak terhubung: pesan otomatis & broadcast ikut berhenti.
                'count' => WaDevice::whereIn('branch_id', $branchIds)->whereNotNull('jid')->where('status', '!=', 'connected')->count(),
                'label' => 'nomor WhatsApp terputus',
                'icon' => 'ri-wifi-off-line', 'color' => 'secondary',
                'url' => route('whatsapp.devices.index'),
            ],
        ];

        return collect($items)->filter(fn ($item) => $item['count'] > 0)
            ->map(fn ($item) => [...$item, 'note' => isset($item['note']) ? ($item['note'])() : null])
            ->values()->all();
    }

    private function today($branchIds)
    {
        return ScheduleSession::whereIn('branch_id', $branchIds)
            ->with(['branch:id,name', 'teacher:id,name', 'room:id,name', 'grade:id,name,category_id', 'grade.category:id,name,subject_id', 'grade.category.subject:id,name', 'attendances.student:id,name'])
            ->whereBetween('start_at', [today(), today()->endOfDay()])
            ->orderBy('start_at')
            ->limit(15)
            ->get();
    }
}

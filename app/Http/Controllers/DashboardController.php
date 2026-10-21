<?php

namespace App\Http\Controllers;

use App\Models\CatalogOrder;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\SchedulePortalRequest;
use App\Models\ScheduleRoutine;
use App\Models\ScheduleSession;
use App\Models\TicketOrder;
use App\Models\UserCompany;
use App\Models\UserBranch;
use App\Models\WaChat;
use App\Models\WaDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Dashboard owner: ringkasan bulan ini, hal yang perlu ditindaklanjuti, jadwal hari ini, dan aktivitas terbaru.
 * Semua dibatasi ke branch & perusahaan miliknya.
 * Tim platform (superadmin/staff) menyusul di tahap dashboard superadmin.
 */
class DashboardController extends Controller
{
    /** Pesanan katalog yang dihitung sebagai pemasukan. */
    private const CATALOG_SOLD = [CatalogOrder::STATUS_PAID, CatalogOrder::STATUS_PROCESSING, CatalogOrder::STATUS_COMPLETED];

    public function __invoke(Request $request): View
    {
        $user = $request->user();

        if ($user->isPlatformTeam()) {
            return view('dashboard', ['owner' => false]);
        }

        $branchIds = UserBranch::where('user_id', $user->getKey())->pluck('id');
        $companyIds = UserCompany::where('user_id', $user->getKey())->pluck('id');

        return view('dashboard', [
            'owner' => true,
            'hasBranch' => $branchIds->isNotEmpty(),
            'actions' => $branchIds->isEmpty() ? [] : $this->actions($branchIds),
            'today' => $this->today($branchIds),
            'usesSchedule' => $branchIds->isNotEmpty() && ScheduleRoutine::whereIn('branch_id', $branchIds)->exists(),
            'multiBranch' => $branchIds->count() > 1,
            'stats' => $this->stats($branchIds, $companyIds),
            'recent' => $this->recent($branchIds, $companyIds),
            'balance' => (int) $user->wallet?->balance,
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

    /** Kartu angka bulan ini, dibandingkan dengan bulan lalu sampai tanggal yang sama. */
    private function stats(Collection $branchIds, Collection $companyIds): array
    {
        $start = now()->startOfMonth();
        $lastStart = $start->copy()->subMonthNoOverflow();
        $lastEnd = $lastStart->copy()->addDays(now()->day - 1)->setTimeFrom(now())->min($start);

        $income = fn (Carbon $from, Carbon $to) => [
            'ticket' => (int) TicketOrder::whereIn('company_id', $companyIds)->where('status', TicketOrder::STATUS_PAID)->whereBetween('paid_at', [$from, $to])->sum('total'),
            'catalog' => (int) CatalogOrder::whereIn('company_id', $companyIds)->whereIn('status', self::CATALOG_SOLD)->whereBetween('created_at', [$from, $to])->sum('total'),
            'invoice' => (int) Invoice::whereIn('branch_id', $branchIds)->where('status', Invoice::STATUS_PAID)->whereBetween('paid_at', [$from, $to])->sum('paid_amount'),
        ];
        $orders = fn (Carbon $from, Carbon $to) => TicketOrder::whereIn('company_id', $companyIds)->where('status', TicketOrder::STATUS_PAID)->whereBetween('paid_at', [$from, $to])->count()
            + CatalogOrder::whereIn('company_id', $companyIds)->where('status', '!=', CatalogOrder::STATUS_CANCELLED)->whereBetween('created_at', [$from, $to])->count();
        $contacts = fn (Carbon $from, Carbon $to) => Customer::whereIn('branch_id', $branchIds)->whereBetween('created_at', [$from, $to])->count();

        $now = $income($start, now());
        $unpaid = Invoice::whereIn('branch_id', $branchIds)->where('status', Invoice::STATUS_UNPAID);

        return [
            'income' => ['value' => array_sum($now), 'parts' => $now, 'change' => $this->change(array_sum($now), array_sum($income($lastStart, $lastEnd)))],
            'orders' => ['value' => $orders($start, now()), 'change' => $this->change($orders($start, now()), $orders($lastStart, $lastEnd))],
            'unpaid' => ['value' => (clone $unpaid)->count(), 'amount' => (int) (clone $unpaid)->sum('amount')],
            'contacts' => ['value' => $contacts($start, now()), 'change' => $this->change($contacts($start, now()), $contacts($lastStart, $lastEnd))],
        ];
    }

    /** Persen naik/turun; null kalau bulan lalu kosong (tidak bisa dibandingkan). */
    private function change(int $now, int $before): ?int
    {
        return $before > 0 ? (int) round(($now - $before) / $before * 100) : null;
    }

    /** 5 transaksi terakhir dari tiket, katalog, dan invoice. */
    private function recent(Collection $branchIds, Collection $companyIds): Collection
    {
        $rupiah = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.');

        $tickets = TicketOrder::whereIn('company_id', $companyIds)->where('status', TicketOrder::STATUS_PAID)->latest('paid_at')->limit(5)
            ->get(['id', 'page_id', 'name', 'total', 'paid_at'])
            ->map(fn ($o) => ['at' => $o->paid_at, 'icon' => 'ri-coupon-3-line', 'color' => 'primary', 'title' => $o->name.' membeli tiket', 'amount' => $rupiah($o->total), 'url' => route('pages.ticket.orders.show', [$o->page_id, $o->id])]);

        $catalog = CatalogOrder::whereIn('company_id', $companyIds)->latest()->limit(5)
            ->get(['id', 'page_id', 'name', 'total', 'status', 'created_at'])
            ->map(fn ($o) => ['at' => $o->created_at, 'icon' => 'ri-shopping-bag-3-line', 'color' => 'warning', 'title' => $o->name.' memesan produk', 'amount' => $rupiah($o->total), 'badge' => [$o->statusLabel(), $o->statusBadge()], 'url' => route('pages.catalog.orders.show', [$o->page_id, $o->id])]);

        $invoices = Invoice::whereIn('branch_id', $branchIds)->where('status', Invoice::STATUS_PAID)->latest('paid_at')->limit(5)
            ->get(['id', 'number', 'customer_name', 'paid_amount', 'paid_at'])
            ->map(fn ($i) => ['at' => $i->paid_at, 'icon' => 'ri-file-list-3-line', 'color' => 'success', 'title' => $i->customer_name.' membayar invoice '.$i->number, 'amount' => $rupiah($i->paid_amount), 'url' => route('invoices.show', $i->id)]);

        return $tickets->concat($catalog)->concat($invoices)->filter(fn ($row) => $row['at'])->sortByDesc('at')->take(5)->values();
    }
}

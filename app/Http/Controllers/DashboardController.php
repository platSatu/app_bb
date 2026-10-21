<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Portal\Concerns\InPortal;
use App\Http\Controllers\Portal\TeachingController;
use App\Models\CatalogOrder;
use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\Deposit;
use App\Models\Invoice;
use App\Models\InvoiceCategory;
use App\Models\SchedulePortalRequest;
use App\Models\ScheduleRoutine;
use App\Models\ScheduleSession;
use App\Models\TicketOrder;
use App\Models\UserCompany;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\WaChat;
use App\Models\WaDevice;
use App\Models\WalletWithdrawal;
use App\Models\WebContactMessage;
use App\Support\Menu;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Dashboard semua akun. Owner: ringkasan bulan ini, hal yang perlu ditindaklanjuti, jadwal hari ini, dan aktivitas terbaru.
 * Semua dibatasi ke branch & perusahaan miliknya. Peran pengajar/murid ditampilkan di atasnya.
 * Tim platform (superadmin/staff) melihat ringkasan platform, sesuai izin menu masing-masing.
 */
class DashboardController extends Controller
{
    use InPortal;

    /** Pesanan katalog yang dihitung sebagai pemasukan. */
    private const CATALOG_SOLD = [CatalogOrder::STATUS_PAID, CatalogOrder::STATUS_PROCESSING, CatalogOrder::STATUS_COMPLETED];

    public function __invoke(Request $request): View
    {
        $user = $request->user();

        if ($user->isPlatformTeam()) {
            return $this->admin($user);
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
            'usesInvoice' => $branchIds->isNotEmpty() && InvoiceCategory::whereIn('branch_id', $branchIds)->exists(),
            'chart' => $this->incomeChart($branchIds, $companyIds),
            'recent' => $this->recent($branchIds, $companyIds),
            'balance' => (int) $user->wallet?->balance,
            'portal' => $this->portal($user),
        ]);
    }

    /** Kartu peran pengajar & murid/orang tua (status pendaftaran, mengajar hari ini, les berikutnya). */
    private function portal(User $user): array
    {
        $studentIds = $this->students($user)->modelKeys();
        $teacherIds = $this->teachers($user)->modelKeys();

        return [
            'roles' => ['student' => $studentIds !== [], 'teacher' => $teacherIds !== []],
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
        ];
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

    /** Pemasukan 8 minggu terakhir per sumber; sumber yang kosong semua tidak ikut digambar. */
    private function incomeChart(Collection $branchIds, Collection $companyIds): array
    {
        $series = collect([
            'Tiket' => $this->weekly(TicketOrder::whereIn('company_id', $companyIds)->where('status', TicketOrder::STATUS_PAID), 'paid_at', 'total'),
            'Katalog' => $this->weekly(CatalogOrder::whereIn('company_id', $companyIds)->whereIn('status', self::CATALOG_SOLD), 'created_at', 'total'),
            'Invoice' => $this->weekly(Invoice::whereIn('branch_id', $branchIds)->where('status', Invoice::STATUS_PAID), 'paid_at', 'paid_amount'),
        ])->filter(fn ($data) => array_sum($data) > 0)->map(fn ($data, $name) => ['name' => $name, 'data' => $data])->values();

        return ['labels' => $this->weekLabels(), 'series' => $series->all()];
    }

    private function weekStart(): Carbon
    {
        return now()->startOfWeek()->subWeeks(7);
    }

    private function weekLabels(): array
    {
        return collect(range(0, 7))->map(fn ($i) => $this->weekStart()->addWeeks($i)->translatedFormat('d M'))->all();
    }

    /** Jumlah (atau total kolom) per minggu, 8 minggu terakhir. Dikelompokkan per tanggal di DB, lalu per minggu di PHP. */
    private function weekly(Builder $query, string $dateColumn, ?string $sumColumn = null): array
    {
        $start = $this->weekStart();
        $values = array_fill(0, 8, 0);

        $query->where($dateColumn, '>=', $start)
            ->selectRaw("DATE({$dateColumn}) as day, ".($sumColumn ? "SUM({$sumColumn})" : 'COUNT(*)').' as total')
            ->groupBy('day')
            ->pluck('total', 'day')
            ->each(function ($total, $day) use ($start, &$values) {
                $week = (int) floor($start->diffInDays(Carbon::parse($day), false) / 7);
                if ($week >= 0 && $week < 8) {
                    $values[$week] += (int) $total;
                }
            });

        return $values;
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

    /** Superadmin & staff: setiap kartu hanya muncul kalau role-nya boleh membuka menu terkait. */
    private function admin(User $user): View
    {
        $can = fn (string $key, string $ability = Menu::ABILITY_VIEW) => $user->canAccess($key, $ability);
        $start = now()->startOfMonth();
        $rupiah = fn ($v) => 'Rp '.number_format((int) $v, 0, ',', '.');
        $owners = User::where('user_type', User::TYPE_USER);

        $cards = array_filter([
            $can('superadmin.users') ? ['Pengguna baru bulan ini', number_format((clone $owners)->where('created_at', '>=', $start)->count(), 0, ',', '.'),
                'Total '.number_format((clone $owners)->count(), 0, ',', '.').' pemilik usaha', 'ri-user-add-line', 'primary', route('superadmin.users.index')] : null,
            $can('superadmin.companies') ? ['Perusahaan aktif', number_format(UserCompany::where('status', UserCompany::STATUS_ACTIVE)->count(), 0, ',', '.'),
                UserBranch::where('status', UserBranch::STATUS_ACTIVE)->count().' branch aktif', 'ri-building-4-line', 'info', route('superadmin.companies.index')] : null,
            $can('superadmin.deposits') ? ['Top up bulan ini', $rupiah(Deposit::where('status', Deposit::STATUS_SUCCESS)->where('paid_at', '>=', $start)->sum('amount')),
                Deposit::where('status', Deposit::STATUS_SUCCESS)->where('paid_at', '>=', $start)->count().' transaksi berhasil', 'ri-wallet-3-line', 'success', route('superadmin.deposits.index')] : null,
            $can('superadmin.withdrawals', Menu::ABILITY_MANAGE) ? ['Penarikan bulan ini', $rupiah(WalletWithdrawal::where('status', WalletWithdrawal::STATUS_SUCCESS)->where('updated_at', '>=', $start)->sum('amount')),
                'Sudah berhasil dikirim ke rekening', 'ri-bank-line', 'warning', route('superadmin.withdrawals.index')] : null,
        ]);

        $actions = collect([
            $can('superadmin.withdrawals', Menu::ABILITY_MANAGE) ? [
                WalletWithdrawal::whereIn('status', [WalletWithdrawal::STATUS_PENDING, WalletWithdrawal::STATUS_NEEDS_REVIEW])->count(),
                'penarikan saldo menunggu diproses', 'ri-hand-coin-line', 'danger', route('superadmin.withdrawals.index'),
            ] : null,
            $can('superadmin.withdrawals', Menu::ABILITY_MANAGE) ? [
                BankAccount::where('status', BankAccount::STATUS_PENDING)->count(),
                'rekening bank menunggu dicek', 'ri-bank-card-line', 'warning', route('superadmin.bank-accounts.index'),
            ] : null,
            $can('superadmin.web.contact-messages') ? [
                WebContactMessage::where('status', 'new')->count(),
                'pesan baru dari website', 'ri-mail-unread-line', 'primary', route('superadmin.web.contact-messages.index'),
            ] : null,
            $can('superadmin.pages') ? [
                TicketOrder::where('status', TicketOrder::STATUS_REFUND_NEEDED)->count(),
                'pesanan tiket perlu refund', 'ri-refund-2-line', 'danger', route('superadmin.pages.index'),
            ] : null,
        ])->filter(fn ($item) => $item && $item[0] > 0)->values();

        return view('dashboard.admin', [
            'chart' => $can('superadmin.users') ? ['labels' => $this->weekLabels(), 'series' => [
                ['name' => 'Pendaftar baru', 'data' => $this->weekly(User::where('user_type', User::TYPE_USER), 'created_at')],
            ]] : null,
            'cards' => array_values($cards),
            'actions' => $actions,
            'newUsers' => $can('superadmin.users') ? (clone $owners)->with('company:id,user_id,name')->latest()->limit(6)->get(['id', 'name', 'email', 'created_at']) : null,
        ]);
    }
}

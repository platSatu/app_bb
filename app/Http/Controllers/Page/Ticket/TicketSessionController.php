<?php

namespace App\Http\Controllers\Page\Ticket;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Models\PageType;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketScanner;
use App\Models\TicketSession;
use App\Models\TicketSessionAttendance;
use App\Services\Ticket\TicketAttendanceService;
use App\Support\Menu;
use App\Support\XlsxExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Tab Sesi & Absensi: jadwal kelas + jendela scan, link petugas absensi, rekap hadir per sesi. */
class TicketSessionController extends Controller
{
    use ResolvesPage;

    private const PER_PAGE = 30;

    public function index(Request $request, TicketAttendanceService $attendance, string $page): View
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET);
        $sessions = TicketSession::where('page_id', $model->getKey())->orderBy('starts_at')->get();

        return view('page.ticket.sessions', [
            'page' => $model,
            'sessions' => $sessions,
            'stats' => $sessions->mapWithKeys(fn ($s) => [$s->getKey() => $attendance->stats($s)]),
            'categories' => TicketCategory::where('page_id', $model->getKey())->orderBy('sort_order')->get(['id', 'name']),
            'scanners' => TicketScanner::where('page_id', $model->getKey())->where('type', TicketScanner::TYPE_ATTENDANCE)->latest()->get(),
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function store(Request $request, string $page): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET, Menu::ABILITY_MANAGE);
        $session = new TicketSession($this->validated($request, $model->getKey()));
        $session->page_id = $model->getKey();
        $session->save();

        return back()->with('success', "Sesi {$session->name} berhasil ditambahkan.");
    }

    public function update(Request $request, string $page, string $session): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET, Menu::ABILITY_MANAGE);
        $item = TicketSession::where('page_id', $model->getKey())->findOrFail($session);
        $item->fill($this->validated($request, $model->getKey(), "session-{$item->getKey()}"))->save();

        return back()->with('success', "Sesi {$item->name} berhasil diperbarui.");
    }

    public function destroy(Request $request, string $page, string $session): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET, Menu::ABILITY_MANAGE);
        $item = TicketSession::where('page_id', $model->getKey())->findOrFail($session);

        // Data kehadiran tidak boleh hilang karena salah klik.
        if ($item->attendances()->exists()) {
            return back()->with('error', "Sesi {$item->name} sudah ada absensinya, jadi tidak bisa dihapus. Nonaktifkan saja kalau tidak dipakai.");
        }

        $item->delete();

        return back()->with('success', "Sesi {$item->name} dihapus.");
    }

    /** Daftar hadir / belum hadir satu sesi (+ unduh Excel). */
    public function show(Request $request, TicketAttendanceService $attendance, string $page, string $session): View|StreamedResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET);
        $item = TicketSession::where('page_id', $model->getKey())->findOrFail($session);
        $tab = $request->query('tab') === 'absent' ? 'absent' : 'present';
        $search = trim((string) $request->query('search', ''));
        $like = '%'.addcslashes($search, '%_\\').'%';

        $query = $tab === 'present'
            ? TicketSessionAttendance::where('session_id', $item->getKey())
                ->with(['ticket:id,holder_name,category_name,booking_code,number', 'scanner:id,name'])
                ->when($search !== '', fn ($q) => $q->whereHas('ticket', fn ($t) => $t->where(fn ($w) => $w->where('holder_name', 'like', $like)->orWhere('booking_code', $search))))
                ->orderByDesc('scanned_at')
            : $item->eligibleTickets()
                ->whereNotIn('id', TicketSessionAttendance::select('ticket_id')->where('session_id', $item->getKey()))
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('holder_name', 'like', $like)->orWhere('booking_code', $search)))
                ->orderBy('holder_name');

        if ($request->boolean('export')) {
            $name = Str::slug($model->name.'-'.$item->name).'-'.($tab === 'present' ? 'hadir' : 'belum-hadir');

            return $tab === 'present'
                ? XlsxExport::download($name, ['Nama', 'Kategori', 'No. Tiket', 'Kode Booking', 'Waktu Absen', 'Petugas'],
                    $query->lazy()->map(fn ($a) => [$a->ticket?->holder_name, $a->ticket?->category_name, $a->ticket?->number, $a->ticket?->booking_code, $a->scanned_at->format('Y-m-d H:i:s'), $a->scanner?->name]))
                : XlsxExport::download($name, ['Nama', 'Kategori', 'No. Tiket', 'Kode Booking', 'Punya ID Card'],
                    $query->lazy()->map(fn (Ticket $t) => [$t->holder_name, $t->category_name, $t->number, $t->booking_code, $t->hasCard() ? 'Ya' : 'Belum']));
        }

        return view('page.ticket.session-show', [
            'page' => $model,
            'session' => $item,
            'tab' => $tab,
            'rows' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'stats' => $attendance->stats($item),
            'categories' => TicketCategory::where('page_id', $model->getKey())->pluck('name', 'id'),
            'routes' => $this->routes($request),
        ]);
    }

    private function validated(Request $request, string $pageId, string $bag = 'session-new'): array
    {
        $data = $request->validateWithBag($bag, [
            'name' => ['required', 'string', 'max:150'],
            'room' => ['nullable', 'string', 'max:100'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'open_before_minutes' => ['required', 'integer', 'min:0', 'max:720'],
            'late_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['uuid', Rule::exists('ticket_categories', 'id')->where('page_id', $pageId)],
            'status' => ['required', Rule::in([TicketSession::STATUS_ACTIVE, TicketSession::STATUS_INACTIVE])],
        ], [
            'ends_at.after' => 'Jam selesai harus setelah jam mulai.',
            'open_before_minutes.max' => 'Absensi paling cepat dibuka 12 jam sebelum mulai.',
        ], [
            'name' => 'nama sesi', 'room' => 'ruangan', 'starts_at' => 'jam mulai', 'ends_at' => 'jam selesai',
            'open_before_minutes' => 'waktu buka absensi', 'late_minutes' => 'batas terlambat',
        ]);

        $data['category_ids'] = array_values(array_unique($data['category_ids'] ?? [])) ?: null;
        $data['room'] = filled($data['room'] ?? null) ? trim($data['room']) : null;

        return $data;
    }
}

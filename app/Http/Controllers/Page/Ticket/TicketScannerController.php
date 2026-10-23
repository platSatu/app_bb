<?php

namespace App\Http\Controllers\Page\Ticket;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\Ticket;
use App\Models\TicketScanner;
use App\Services\Ticket\TicketCheckinService;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Tab Registrasi Ulang: kelola link petugas scan + pantau kehadiran. Store/update/destroy juga dipakai link kiosk & absensi. */
class TicketScannerController extends Controller
{
    use ResolvesPage;

    public function index(Request $request, TicketCheckinService $checkins, string $page): View
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET);

        return view('page.ticket.scanners', [
            'page' => $model,
            'scanners' => TicketScanner::where('page_id', $model->getKey())->where('type', TicketScanner::TYPE_CHECKIN)->latest()->get(),
            'stats' => $checkins->stats($model),
            'recent' => Ticket::where('page_id', $model->getKey())->whereNotNull('checked_in_at')->with('scanner:id,name')->latest('checked_in_at')->limit(30)->get(),
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function store(Request $request, string $page): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET, Menu::ABILITY_MANAGE);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(TicketScanner::TYPES)],
        ], [], ['name' => 'nama petugas / perangkat']);
        $name = $data['name'];

        $scanner = new TicketScanner(['name' => $name, 'status' => TicketScanner::STATUS_ACTIVE]);
        $scanner->page_id = $model->getKey();
        $scanner->type = $data['type'] ?? TicketScanner::TYPE_CHECKIN;
        $scanner->token = Str::random(48);
        $pin = $this->newPin($scanner);
        $scanner->save();

        return back()->with('scanner_pin', ['name' => $scanner->name, 'pin' => $pin, 'url' => $scanner->url(), 'type' => $scanner->type])
            ->with('success', "Link {$scanner->name} berhasil dibuat.");
    }

    public function update(Request $request, string $page, string $scanner): RedirectResponse
    {
        $item = $this->scanner($request, $page, $scanner);
        $action = $request->validate(['action' => ['required', 'in:toggle,reset_pin']])['action'];

        if ($action === 'reset_pin') {
            $pin = $this->newPin($item);
            $item->save();

            return back()->with('scanner_pin', ['name' => $item->name, 'pin' => $pin, 'url' => $item->url(), 'type' => $item->type])
                ->with('success', "PIN {$item->name} sudah diganti. PIN lama tidak berlaku lagi.");
        }

        $item->status = $item->isActive() ? TicketScanner::STATUS_INACTIVE : TicketScanner::STATUS_ACTIVE;
        $item->save();

        return back()->with('success', $item->isActive() ? "Link {$item->name} aktif kembali." : "Link {$item->name} dinonaktifkan. Halamannya langsung tidak bisa dipakai.");
    }

    public function destroy(Request $request, string $page, string $scanner): RedirectResponse
    {
        $item = $this->scanner($request, $page, $scanner);
        $item->delete();

        return back()->with('success', "Link {$item->name} dihapus. Riwayat scan tetap tersimpan.");
    }

    private function scanner(Request $request, string $page, string $id): TicketScanner
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET, Menu::ABILITY_MANAGE);

        return TicketScanner::where('page_id', $model->getKey())->findOrFail($id);
    }

    /** PIN 6 angka hanya ditampilkan sekali; yang disimpan hanya hash-nya. */
    private function newPin(TicketScanner $scanner): string
    {
        $pin = (string) random_int(100000, 999999);
        $scanner->pin_hash = Hash::make($pin);

        return $pin;
    }
}

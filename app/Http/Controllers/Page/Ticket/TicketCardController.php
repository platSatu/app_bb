<?php

namespace App\Http\Controllers\Page\Ticket;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\Ticket;
use App\Models\TicketCardLog;
use App\Models\TicketScanner;
use App\Services\Ticket\TicketCardService;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Tab ID Card: pengaturan cetak, link kiosk cetak mandiri, daftar peserta + cetak ulang oleh admin. */
class TicketCardController extends Controller
{
    use ResolvesPage;

    private const PER_PAGE = 25;

    public function index(Request $request, TicketCardService $cards, string $page): View
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET);
        $settings = TicketCardService::settings($model);
        $search = trim((string) $request->query('search', ''));
        $status = in_array($request->query('status'), ['printed', 'waiting'], true) ? $request->query('status') : null;

        $tickets = Ticket::where('page_id', $model->getKey())
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('holder_name', 'like', '%'.addcslashes($search, '%_\\').'%')
                ->orWhere('booking_code', $search)->orWhere('number', strtoupper($search))))
            ->when($status === 'printed', fn ($q) => $q->whereNotNull('card_printed_at'))
            ->when($status === 'waiting', fn ($q) => $q->whereNull('card_printed_at'))
            ->orderBy('holder_name')->orderBy('sequence')
            ->paginate(self::PER_PAGE)->withQueryString();

        $counts = Ticket::where('page_id', $model->getKey())
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN card_printed_at IS NULL THEN 0 ELSE 1 END) as printed')->first();

        return view('page.ticket.cards', [
            'page' => $model,
            'settings' => $settings,
            'preview' => $cards->cardData($model, $settings, null),
            'kiosks' => TicketScanner::where('page_id', $model->getKey())->where('type', TicketScanner::TYPE_KIOSK)->latest()->get(),
            'tickets' => $tickets,
            'total' => (int) ($counts->total ?? 0),
            'printed' => (int) ($counts->printed ?? 0),
            'logs' => TicketCardLog::where('page_id', $model->getKey())->with(['ticket:id,holder_name,number', 'scanner:id,name', 'actor:id,name'])->latest('created_at')->limit(15)->get(),
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function update(Request $request, string $page): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET, Menu::ABILITY_MANAGE);
        $data = $request->validate([
            'enabled' => ['boolean'],
            'size' => ['required', Rule::in(array_keys(TicketCardService::SIZES))],
            'orientation' => ['required', Rule::in(array_keys(TicketCardService::ORIENTATIONS))],
            'label' => ['required', 'string', 'max:24'],
            'show_category' => ['boolean'],
            'hole' => ['boolean'],
        ], ['label.max' => 'Tulisan di kartu maksimal 24 huruf supaya tetap rapi.'], ['label' => 'tulisan di kartu']);

        $card = [
            'enabled' => $request->boolean('enabled'),
            'size' => $data['size'],
            'orientation' => $data['orientation'],
            'label' => mb_strtoupper(trim($data['label'])),
            'show_category' => $request->boolean('show_category'),
            'hole' => $request->boolean('hole'),
        ];

        // Dikunci supaya tidak saling menimpa dengan pengaturan tiket lain (desain, S&K).
        DB::transaction(function () use ($model, $card) {
            $locked = PageCategory::whereKey($model->getKey())->lockForUpdate()->firstOrFail();
            $settings = $locked->settings ?? [];
            data_set($settings, 'ticket.card', $card);
            $locked->settings = $settings;
            $locked->save();
        });

        return back()->with('success', $card['enabled']
            ? 'Pengaturan ID card disimpan. Peserta sudah bisa mencetak ID card di kiosk.'
            : 'Pengaturan ID card disimpan. Cetak di kiosk masih ditutup sampai Anda mengaktifkannya.');
    }

    /** Cetak dari admin (cetak pertama atau cetak ulang). Selalu tercatat di riwayat. */
    public function print(Request $request, TicketCardService $cards, string $page, string $ticket): Response
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET, Menu::ABILITY_MANAGE);
        $item = Ticket::where('page_id', $model->getKey())->findOrFail($ticket);

        $item = $cards->issue($item, TicketCardLog::SOURCE_ADMIN, null, $request->user()->getKey(), replace: $request->boolean('replace'));

        return response($cards->render($model, $item, autoPrint: true));
    }
}

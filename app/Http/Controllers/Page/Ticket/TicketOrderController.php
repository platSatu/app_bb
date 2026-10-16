<?php

namespace App\Http\Controllers\Page\Ticket;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Models\PageType;
use App\Models\TicketOrder;
use App\Services\Ticket\TicketOrderService;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TicketOrderController extends Controller
{
    use ResolvesPage;

    public function index(Request $request, string $page): View
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET);
        $search = trim((string) $request->query('search'));

        $orders = TicketOrder::where('page_id', $model->getKey())
            ->withSum('items as ticket_count', 'quantity')
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $summary = TicketOrder::where('page_id', $model->getKey())->where('status', TicketOrder::STATUS_PAID)
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total), 0) as revenue')->first();

        return view('page.ticket.orders.index', [
            'page' => $model,
            'orders' => $orders,
            'summary' => $summary,
            'ticketsSold' => (int) $model->ticketCategories()->sum('sold'),
            'routes' => $this->routes($request),
        ]);
    }

    public function show(Request $request, string $page, string $order): View
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET);

        return view('page.ticket.orders.show', [
            'page' => $model,
            'order' => TicketOrder::where('page_id', $model->getKey())->with(['items', 'tickets', 'customer'])->findOrFail($order),
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function resend(Request $request, TicketOrderService $orders, string $page, string $order): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET, Menu::ABILITY_MANAGE);
        $item = TicketOrder::where('page_id', $model->getKey())->where('status', TicketOrder::STATUS_PAID)->findOrFail($order);

        return $orders->sendTickets($item)
            ? back()->with('success', "E-ticket dikirim ulang ke {$item->email}.")
            : back()->with('error', 'Email belum bisa dikirim. Coba lagi beberapa saat, atau bagikan link e-ticket langsung ke pembeli.');
    }
}

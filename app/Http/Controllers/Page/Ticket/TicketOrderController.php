<?php

namespace App\Http\Controllers\Page\Ticket;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Models\PageType;
use App\Models\PaymentCallback;
use App\Models\TicketEmailLog;
use App\Models\TicketOrder;
use App\Models\TicketStockLog;
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
        $item = TicketOrder::where('page_id', $model->getKey())->with(['items', 'tickets', 'customer', 'submission.answers'])->findOrFail($order);

        return view('page.ticket.orders.show', [
            'page' => $model,
            'order' => $item,
            'timeline' => $this->timeline($item),
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function resend(Request $request, TicketOrderService $orders, string $page, string $order): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET, Menu::ABILITY_MANAGE);
        $item = TicketOrder::where('page_id', $model->getKey())->where('status', TicketOrder::STATUS_PAID)->findOrFail($order);

        return $orders->sendTickets($item, 'resend', $request->user()->getKey())
            ? back()->with('success', "E-ticket dikirim ulang ke {$item->email}.")
            : back()->with('error', 'Email belum bisa dikirim. Coba lagi beberapa saat, atau bagikan link e-ticket langsung ke pembeli.');
    }

    /** Urutan kejadian satu pesanan dari catatan asli: stok, callback Duitku, email. */
    private function timeline(TicketOrder $order)
    {
        $rupiah = fn (int $n) => $n > 0 ? 'Rp '.number_format($n, 0, ',', '.') : 'Gratis';

        return collect([['at' => $order->created_at, 'title' => 'Pesanan dibuat', 'detail' => 'Total '.$rupiah($order->total), 'badge' => null]])
            ->merge(TicketStockLog::where('order_id', $order->getKey())->with('category:id,name')->get()->map(fn ($log) => [
                'at' => $log->created_at,
                'title' => 'Stok · '.$log->actionLabel(),
                'detail' => trim(($log->category?->name ?? '').' · '.$log->changes().($log->note ? ' · '.$log->note : ''), ' ·'),
                'badge' => null,
            ]))
            ->merge(PaymentCallback::where('provider', 'duitku')->where('merchant_order_id', $order->reference)->get()->map(fn ($log) => [
                'at' => $log->created_at,
                'title' => 'Callback Duitku',
                'detail' => $rupiah($log->amount()).' · '.($log->payload['paymentCode'] ?? '-').' · kode '.($log->payload['resultCode'] ?? '-').($log->message ? ' · '.$log->message : ''),
                'badge' => [$log->resultLabel(), $log->resultBadge()],
            ]))
            ->merge(TicketEmailLog::where('order_id', $order->getKey())->with('actor:id,name')->get()->map(fn ($log) => [
                'at' => $log->created_at,
                'title' => 'Email e-ticket · '.$log->triggerLabel(),
                'detail' => $log->email.($log->actor ? ' · oleh '.$log->actor->name : '').($log->error ? ' · '.$log->error : ''),
                'badge' => [$log->statusLabel(), $log->statusBadge()],
            ]))
            ->sortBy(fn ($row) => $row['at']?->getTimestamp() ?? 0)
            ->values();
    }
}

<?php

namespace App\Http\Controllers\Page\Ticket;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\PaymentCallback;
use App\Models\TicketCategory;
use App\Models\TicketEmailLog;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketStockLog;
use App\Support\XlsxExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Laporan tiket (pembeli, stok, callback Duitku, email). Semua query dikunci ke page milik user. */
class TicketReportController extends Controller
{
    use ResolvesPage;

    private const PER_PAGE = 25;

    /** Per kategori: tab Pembeli & Riwayat Stok. */
    public function sales(Request $request, string $page, string $category): View|StreamedResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET);
        $item = TicketCategory::where('page_id', $model->getKey())->findOrFail($category);
        $filters = $this->filters($request, ['buyers', 'stock']);

        $query = $filters['tab'] === 'stock'
            ? $this->stockQuery($model, $filters)->where('category_id', $item->getKey())
            : $this->buyersQuery($item, $filters);

        if ($request->boolean('export')) {
            $name = Str::slug($model->name.'-'.$item->name).'-'.($filters['tab'] === 'stock' ? 'riwayat-stok' : 'pembeli');

            return $filters['tab'] === 'stock' ? $this->exportStock($query, $name) : $this->exportBuyers($query, $name);
        }

        return view('page.ticket.sales', [
            'page' => $model,
            'category' => $item,
            'filters' => $filters,
            'rows' => $query->paginate(self::PER_PAGE)->withQueryString(),
            'routes' => $this->routes($request),
        ]);
    }

    /** Seluruh page: tab Pembayaran (callback Duitku), Email, Stok. */
    public function index(Request $request, string $page): View|StreamedResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET);
        $filters = $this->filters($request, ['payments', 'emails', 'stock']);

        $query = match ($filters['tab']) {
            'emails' => $this->emailQuery($model, $filters),
            'stock' => $this->stockQuery($model, $filters),
            default => $this->paymentQuery($model, $filters),
        };

        if ($request->boolean('export')) {
            $name = Str::slug($model->name).'-'.['payments' => 'callback-duitku', 'emails' => 'email', 'stock' => 'riwayat-stok'][$filters['tab']];

            return match ($filters['tab']) {
                'emails' => $this->exportEmails($query, $name),
                'stock' => $this->exportStock($query, $name),
                default => $this->exportPayments($query, $name, $model),
            };
        }

        $rows = $query->paginate(self::PER_PAGE)->withQueryString();

        return view('page.ticket.report', [
            'page' => $model,
            'filters' => $filters,
            'rows' => $rows,
            'orders' => $filters['tab'] === 'payments' ? $this->ordersByReference($model, $rows->pluck('merchant_order_id')) : collect(),
            'routes' => $this->routes($request),
        ]);
    }

    private function filters(Request $request, array $tabs): array
    {
        $data = $request->validate([
            'tab' => ['nullable', Rule::in($tabs)],
            'status' => ['nullable', 'string', 'max:30'],
            'search' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ], ['to.after_or_equal' => 'Tanggal akhir tidak boleh sebelum tanggal awal.']);

        return [
            'tab' => $data['tab'] ?? $tabs[0],
            'status' => $data['status'] ?? null,
            'search' => trim((string) ($data['search'] ?? '')),
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
        ];
    }

    private function period(Builder $query, array $filters, string $column = 'created_at'): Builder
    {
        return $query
            ->when($filters['from'], fn ($q, $from) => $q->where($column, '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'], fn ($q, $to) => $q->where($column, '<=', Carbon::parse($to)->endOfDay()));
    }

    private function buyersQuery(TicketCategory $category, array $filters): Builder
    {
        $search = $filters['search'];

        return TicketOrderItem::query()
            ->where('category_id', $category->getKey())
            ->whereHas('order', fn ($q) => $this->period($q->where('status', TicketOrder::STATUS_PAID), $filters, 'paid_at')
                ->when($search !== '', fn ($w) => $w->where(fn ($s) => $s
                    ->where('reference', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%"))))
            ->with('order:id,reference,name,email,phone,paid_at,payment_method')
            ->latest();
    }

    private function stockQuery(PageCategory $page, array $filters): Builder
    {
        return $this->period(TicketStockLog::where('page_id', $page->getKey()), $filters)
            ->when($filters['status'] && isset(TicketStockLog::ACTION_LABELS[$filters['status']]), fn ($q) => $q->where('action', $filters['status']))
            ->with(['category:id,name', 'order:id,reference', 'actor:id,name'])
            ->latest('created_at')->orderByDesc('id');
    }

    private function emailQuery(PageCategory $page, array $filters): Builder
    {
        $search = $filters['search'];

        return $this->period(TicketEmailLog::where('page_id', $page->getKey()), $filters)
            ->when($filters['status'] && isset(TicketEmailLog::STATUS_LABELS[$filters['status']]), fn ($q) => $q->where('status', $filters['status']))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('email', 'like', "%{$search}%")
                ->orWhereHas('order', fn ($o) => $o->where('reference', 'like', "%{$search}%"))))
            ->with(['order:id,reference', 'actor:id,name'])
            ->latest();
    }

    private function paymentQuery(PageCategory $page, array $filters): Builder
    {
        $search = $filters['search'];

        return $this->period(PaymentCallback::where('provider', 'duitku'), $filters)
            ->whereIn('merchant_order_id', TicketOrder::where('page_id', $page->getKey())->select('reference'))
            ->when($filters['status'] && isset(PaymentCallback::RESULT_LABELS[$filters['status']]), fn ($q) => $q->where('result', $filters['status']))
            ->when($search !== '', fn ($q) => $q->where('merchant_order_id', 'like', "%{$search}%"))
            ->latest();
    }

    private function ordersByReference(PageCategory $page, $references)
    {
        return TicketOrder::where('page_id', $page->getKey())->whereIn('reference', $references->filter()->unique())->get(['id', 'reference', 'name'])->keyBy('reference');
    }

    private function exportBuyers(Builder $query, string $name): StreamedResponse
    {
        return XlsxExport::download($name, ['Nomor Pesanan', 'Nama', 'Email', 'WhatsApp', 'Jumlah Tiket', 'Harga Satuan', 'Subtotal', 'Metode Bayar', 'Waktu Lunas'],
            $query->lazy(500)->map(fn ($sale) => [
                $sale->order?->reference, $sale->order?->name, $sale->order?->email, $sale->order?->phone,
                (int) $sale->quantity, (int) $sale->price, (int) $sale->subtotal,
                $sale->order?->payment_method, $sale->order?->paid_at?->format('Y-m-d H:i:s'),
            ]));
    }

    private function exportStock(Builder $query, string $name): StreamedResponse
    {
        return XlsxExport::download($name, ['Waktu', 'Kategori', 'Kejadian', 'Nomor Pesanan', 'Oleh', 'Jumlah Dijual (sebelum)', 'Jumlah Dijual (sesudah)', 'Ditahan (sebelum)', 'Ditahan (sesudah)', 'Terjual (sebelum)', 'Terjual (sesudah)', 'Catatan'],
            $query->lazy(500)->map(fn ($log) => [
                $log->created_at?->format('Y-m-d H:i:s'), $log->category?->name, $log->actionLabel(), $log->order?->reference,
                $log->actor?->name ?? ($log->order ? 'Sistem' : '-'),
                (int) $log->quota_before, (int) $log->quota_after, (int) $log->reserved_before, (int) $log->reserved_after,
                (int) $log->sold_before, (int) $log->sold_after, $log->note,
            ]));
    }

    private function exportEmails(Builder $query, string $name): StreamedResponse
    {
        return XlsxExport::download($name, ['Waktu Dibuat', 'Nomor Pesanan', 'Email Tujuan', 'Pemicu', 'Oleh', 'Status', 'Waktu Terkirim', 'Keterangan Error'],
            $query->lazy(500)->map(fn ($log) => [
                $log->created_at?->format('Y-m-d H:i:s'), $log->order?->reference, $log->email, $log->triggerLabel(),
                $log->actor?->name ?? 'Sistem', $log->statusLabel(), $log->sent_at?->format('Y-m-d H:i:s'), $log->error,
            ]));
    }

    private function exportPayments(Builder $query, string $name, PageCategory $page): StreamedResponse
    {
        return XlsxExport::download($name, ['Waktu Diterima', 'Nomor Pesanan', 'Nominal', 'Kode Hasil Duitku', 'Metode', 'Referensi Duitku', 'Tanda Tangan', 'Hasil Proses', 'Keterangan', 'IP Pengirim'],
            $query->lazy(500)->map(fn ($log) => [
                $log->created_at?->format('Y-m-d H:i:s'), $log->merchant_order_id, $log->amount(),
                $log->payload['resultCode'] ?? '', $log->payload['paymentCode'] ?? '', $log->payload['reference'] ?? '',
                $log->signature_valid ? 'Valid' : 'Tidak valid', $log->resultLabel(), $log->message, $log->ip_address,
            ]));
    }
}

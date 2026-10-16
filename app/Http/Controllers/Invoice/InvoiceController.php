<?php

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Invoice\Concerns\InInvoiceArea;
use App\Models\Invoice;
use App\Models\InvoiceCategory;
use App\Services\Invoice\InvoicePaymentService;
use App\Services\Invoice\InvoiceService;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use RuntimeException;

/** Semua invoice (dashboard & API) + laporan. Dipakai owner dan superadmin (hanya lihat). */
class InvoiceController extends Controller
{
    use InInvoiceArea;

    public function __construct(private readonly InvoiceService $invoices)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $this->isAdmin($request) && ! $this->ownerBranches($request)->count()) {
            return $this->branchRequired();
        }

        $search = trim((string) $request->query('search'));
        $query = Invoice::accessibleBy($user)
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('number', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('customer_email', 'like', "%{$search}%")
                ->orWhere('external_id', 'like', "%{$search}%")))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('source'), fn ($q, $source) => $q->where('source', $source))
            ->when($request->query('category'), fn ($q, $id) => $q->where('category_id', $id))
            ->when($request->query('branch'), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->date('date_from'), fn ($q, $from) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($request->date('date_to'), fn ($q, $to) => $q->where('created_at', '<=', $to->endOfDay()));

        return view('invoice.invoices.index', [
            ...$this->shared($request, 'invoices'),
            'totals' => [
                'unpaid' => (clone $query)->where('status', Invoice::STATUS_UNPAID)->sum('amount'),
                'unpaid_count' => (clone $query)->where('status', Invoice::STATUS_UNPAID)->count(),
                'paid' => (clone $query)->where('status', Invoice::STATUS_PAID)->sum('paid_amount'),
                'fee' => (clone $query)->where('status', Invoice::STATUS_PAID)->sum('fee'),
                'net' => (clone $query)->where('status', Invoice::STATUS_PAID)->sum('net_amount'),
            ],
            'items' => $query->with(['branch:id,name,company_id', 'branch.company:id,name', 'category:id,name'])->latest()->paginate(20)->withQueryString(),
            'categories' => $this->isAdmin($request) ? collect() : InvoiceCategory::accessibleBy($user)->orderBy('name')->get(['id', 'name']),
            'branches' => $this->isAdmin($request) ? collect() : $this->ownerBranches($request),
        ]);
    }

    public function show(Request $request, string $invoice, InvoicePaymentService $payments): View
    {
        $model = Invoice::accessibleBy($request->user())->with(['branch.company', 'category', 'batch', 'customer', 'apiClient'])->findOrFail($invoice);

        // Buka detail invoice yang belum lunas = sekalian cek ke Duitku (maks 1x per 15 detik).
        if ($model->isUnpaid() && Cache::add('invoice-refresh:'.$model->getKey(), 1, 15)) {
            $payments->refresh($model);
            $model->refresh();
        }

        return view('invoice.invoices.show', [
            ...$this->shared($request, 'invoices'),
            'invoice' => $model,
            'payments' => $model->payments()->latest()->get(),
            'webhooks' => $model->api_client_id ? \App\Models\InvoiceWebhookLog::where('invoice_id', $model->getKey())->latest()->limit(20)->get() : collect(),
        ]);
    }

    public function cancel(Request $request, string $invoice): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'max:250']], [], ['reason' => 'alasan'])['reason'];

        try {
            $this->invoices->cancel($this->find($request, $invoice), $reason);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Invoice dibatalkan. Link bayarnya sudah tidak bisa dipakai.');
    }

    public function resend(Request $request, string $invoice): RedirectResponse
    {
        $model = $this->find($request, $invoice);

        if (! $model->isUnpaid()) {
            return back()->with('error', 'Email tagihan hanya untuk invoice yang belum dibayar.');
        }

        return $this->invoices->email($model, $model->emailed_at ? 'reminder' : 'issued')
            ? back()->with('success', "Email tagihan sedang dikirim ke {$model->customer_email}.")
            : back()->with('error', 'Customer ini belum punya email. Bagikan link bayarnya secara manual.');
    }

    public function regenerate(Request $request, string $invoice): RedirectResponse
    {
        $model = $this->find($request, $invoice);
        $this->invoices->regenerateToken($model);

        return back()->with('success', 'Link bayar baru sudah dibuat. Link lama tidak bisa dipakai lagi.');
    }

    private function find(Request $request, string $id): Invoice
    {
        return Invoice::accessibleBy($request->user(), Menu::ABILITY_MANAGE)->findOrFail($id);
    }
}

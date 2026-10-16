<?php

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Invoice\Concerns\InInvoiceArea;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceBatch;
use App\Models\InvoiceCategory;
use App\Services\Invoice\InvoiceService;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/** Invoice per periode: satu periode = satu invoice untuk setiap customer kategori. Khusus owner. */
class BatchController extends Controller
{
    use InInvoiceArea;

    public function __construct(private readonly InvoiceService $invoices)
    {
    }

    public function index(Request $request): View|RedirectResponse
    {
        if (! $this->ownerBranches($request)->count()) {
            return $this->branchRequired();
        }

        $search = trim((string) $request->query('search'));

        return view('invoice.batches.index', [
            ...$this->shared($request, 'batches'),
            'categories' => InvoiceCategory::accessibleBy($request->user())->orderBy('name')->get(['id', 'name']),
            'items' => InvoiceBatch::accessibleBy($request->user())
                ->with('category:id,name')
                ->withCount(['invoices', 'invoices as paid_count' => fn ($q) => $q->where('status', Invoice::STATUS_PAID)])
                ->withSum(['invoices as paid_total' => fn ($q) => $q->where('status', Invoice::STATUS_PAID)], 'paid_amount')
                ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->when($request->query('category'), fn ($q, $id) => $q->where('category_id', $id))
                ->when($request->date('date_from'), fn ($q, $from) => $q->where('due_date', '>=', $from->toDateString()))
                ->when($request->date('date_to'), fn ($q, $to) => $q->where('due_date', '<=', $to->toDateString()))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $categories = InvoiceCategory::accessibleBy($request->user(), Menu::ABILITY_MANAGE)->active()->withCount(['subscriptions' => fn ($q) => $q->active()])->orderBy('name')->get();

        if ($categories->isEmpty()) {
            return redirect()->route('invoices.categories.create')->with('info', 'Buat kategori invoice dulu ya, lalu pilih customer yang akan ditagih.');
        }

        return view('invoice.batches.create', [...$this->shared($request, 'batches'), 'categories' => $categories]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'integer', 'min:1000', 'max:1000000000'],
            'due_date' => ['required', 'date_format:Y-m-d'],
            'use_late_fee' => ['nullable', 'boolean'],
        ], [], ['name' => 'nama periode', 'amount' => 'nominal', 'due_date' => 'jatuh tempo']);

        $category = InvoiceCategory::accessibleBy($request->user(), Menu::ABILITY_MANAGE)->active()->findOrFail($data['category_id']);
        $batch = $this->invoices->createBatch($category, [...$data, 'use_late_fee' => $request->boolean('use_late_fee')], $request->user());
        $count = $batch->invoices()->count();

        return redirect()->route('invoices.batches.show', $batch)->with('success', $count
            ? "{$count} invoice dibuat untuk periode {$batch->name}.".($category->email_enabled ? ' Customer yang punya email sudah dikirimi link bayar.' : '')
            : "Periode {$batch->name} dibuat, tapi kategori ini belum punya customer. Tambahkan customer di bawah.");
    }

    public function show(Request $request, string $batch): View
    {
        $model = $this->find($request, $batch)->load('category');
        $search = trim((string) $request->query('search'));
        $inBatch = $model->invoices()->where('status', '!=', Invoice::STATUS_CANCELLED)->whereNotNull('customer_id')->pluck('customer_id');

        return view('invoice.batches.show', [
            ...$this->shared($request, 'batches'),
            'batch' => $model,
            'summary' => $model->invoices()->selectRaw('status, count(*) as total, sum(amount) as amount, sum(paid_amount) as paid')->groupBy('status')->get()->keyBy('status'),
            'items' => $model->invoices()
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('customer_name', 'like', "%{$search}%")->orWhere('number', 'like', "%{$search}%")))
                ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
                ->orderBy('customer_name')
                ->paginate(50)
                ->withQueryString(),
            'available' => Customer::where('branch_id', $model->branch_id)->whereNotIn('id', $inBatch)->orderBy('name')->limit(1000)->get(['id', 'name', 'phone', 'email']),
        ]);
    }

    public function addCustomers(Request $request, string $batch): RedirectResponse
    {
        $model = $this->find($request, $batch);
        $data = $request->validate([
            'customer_ids' => ['required', 'array', 'max:1000'],
            'customer_ids.*' => ['string', Rule::exists('customers', 'id')->where('branch_id', $model->branch_id)],
        ], ['customer_ids.required' => 'Pilih minimal satu customer dulu ya.']);

        try {
            $count = $this->invoices->addCustomers($model, $data['customer_ids']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$count} invoice ditambahkan ke periode ini.");
    }

    public function copy(Request $request, string $batch): RedirectResponse
    {
        $model = $this->find($request, $batch);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'due_date' => ['required', 'date_format:Y-m-d'],
        ], [], ['name' => 'nama periode', 'due_date' => 'jatuh tempo']);

        $copy = $this->invoices->copyBatch($model, $data, $request->user());

        return redirect()->route('invoices.batches.show', $copy)->with('success', 'Periode berhasil disalin: '.$copy->invoices()->count().' invoice baru dibuat.');
    }

    public function cancel(Request $request, string $batch): RedirectResponse
    {
        $model = $this->find($request, $batch);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:250']], [], ['reason' => 'alasan'])['reason'];
        $count = $this->invoices->cancelBatch($model, $reason);

        return back()->with('success', "Periode dibatalkan. {$count} invoice yang belum dibayar ikut dibatalkan; yang sudah lunas tetap tercatat.");
    }

    private function find(Request $request, string $id): InvoiceBatch
    {
        return InvoiceBatch::accessibleBy($request->user(), Menu::ABILITY_MANAGE)->findOrFail($id);
    }
}

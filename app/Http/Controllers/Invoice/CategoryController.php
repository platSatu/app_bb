<?php

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Invoice\Concerns\InInvoiceArea;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceCategory;
use App\Models\InvoiceSubscription;
use App\Models\UserBranch;
use App\Services\Customer\CustomerService;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Kategori invoice (aturan denda, prefix nomor, email) + customer yang berlangganan. Khusus owner. */
class CategoryController extends Controller
{
    use InInvoiceArea;

    public function index(Request $request): View|RedirectResponse
    {
        if (! ($branches = $this->ownerBranches($request))->count()) {
            return $this->branchRequired();
        }

        $search = trim((string) $request->query('search'));

        return view('invoice.categories.index', [
            ...$this->shared($request, 'categories'),
            'branches' => $branches,
            'items' => InvoiceCategory::accessibleBy($request->user())
                ->with('branch:id,name')
                ->withCount(['subscriptions' => fn ($q) => $q->active(), 'batches'])
                ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
                ->when($request->query('branch'), fn ($q, $id) => $q->where('branch_id', $id))
                ->latest()
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! ($branches = $this->ownerBranches($request))->count()) {
            return $this->branchRequired();
        }

        return $this->form($request, new InvoiceCategory(['status' => 'active', 'prefix' => 'INV', 'late_fee_mode' => 'none', 'late_fee_period' => 'day', 'email_enabled' => true, 'reminder_days' => [3, 1, 0]]), $branches);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['branch_id' => ['required', Rule::in($this->ownerBranches($request)->pluck('id'))]], [], ['branch_id' => 'branch']);
        $category = new InvoiceCategory();
        $category->forceFill(['branch_id' => $request->input('branch_id')]);
        $category->fill($this->validated($request, $category))->save();

        return redirect()->route('invoices.categories.customers', $category)
            ->with('success', "Kategori {$category->name} berhasil dibuat. Sekarang pilih customer yang ditagih di kategori ini.");
    }

    public function edit(Request $request, string $category): View
    {
        return $this->form($request, $this->find($request, $category), collect());
    }

    public function update(Request $request, string $category): RedirectResponse
    {
        $model = $this->find($request, $category);
        $model->fill($this->validated($request, $model))->save();

        return redirect()->route('invoices.categories.index')->with('success', "Kategori {$model->name} berhasil diperbarui.");
    }

    public function destroy(Request $request, string $category): RedirectResponse
    {
        $model = $this->find($request, $category);

        if ($model->batches()->exists() || Invoice::where('category_id', $model->getKey())->exists()) {
            return back()->with('error', "Kategori {$model->name} sudah punya invoice. Supaya riwayat tagihan tetap aman, ubah statusnya menjadi Tidak Aktif saja.");
        }

        $model->delete();

        return back()->with('success', "Kategori {$model->name} sudah dihapus.");
    }

    public function customers(Request $request, string $category): View
    {
        $model = $this->find($request, $category)->load('branch:id,name');
        $subscribed = $model->subscriptions()->pluck('customer_id');

        return view('invoice.categories.customers', [
            ...$this->shared($request, 'categories'),
            'category' => $model,
            'subscriptions' => $model->subscriptions()->with('customer')->latest()->get(),
            'available' => Customer::where('branch_id', $model->branch_id)->whereNotIn('id', $subscribed)->orderBy('name')->limit(1000)->get(['id', 'name', 'phone', 'email']),
        ]);
    }

    public function subscribe(Request $request, string $category): RedirectResponse
    {
        $model = $this->find($request, $category);
        $data = $request->validate([
            'customer_ids' => ['required', 'array', 'max:1000'],
            'customer_ids.*' => ['string', Rule::exists('customers', 'id')->where('branch_id', $model->branch_id)],
        ], ['customer_ids.required' => 'Pilih minimal satu customer dulu ya.']);

        $count = DB::transaction(function () use ($model, $data) {
            $existing = $model->subscriptions()->pluck('customer_id')->all();
            $new = array_diff(array_unique($data['customer_ids']), $existing);

            foreach ($new as $customerId) {
                $this->subscribeCustomer($model, $customerId, null);
            }

            return count($new);
        });

        return back()->with('success', "{$count} customer ditambahkan ke kategori {$model->name}.");
    }

    /** Customer baru langsung masuk Buku Kontak branch (dicocokkan lewat WA/email supaya tidak dobel). */
    public function quickAdd(Request $request, string $category, CustomerService $customers): RedirectResponse
    {
        $model = $this->find($request, $category);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:20', 'regex:/^\+?[0-9\s\-]{8,20}$/'],
            'email' => ['nullable', 'required_without:phone', 'email', 'max:255'],
            'amount' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
        ], ['phone.required_without' => 'Isi nomor WhatsApp atau email.', 'email.required_without' => 'Isi nomor WhatsApp atau email.'], ['name' => 'nama', 'phone' => 'nomor WhatsApp', 'amount' => 'nominal khusus']);

        $customer = DB::transaction(function () use ($model, $data, $customers) {
            $branch = UserBranch::whereKey($model->branch_id)->lockForUpdate()->firstOrFail();
            $customer = $customers->resolve($branch, $data['name'], $data['phone'] ?? null, $data['email'] ?? null, 'Invoice: '.$model->name);

            if (! $model->subscriptions()->where('customer_id', $customer->getKey())->exists()) {
                $this->subscribeCustomer($model, $customer->getKey(), $data['amount'] ?? null);
            }

            return $customer;
        });

        return back()->with('success', "{$customer->displayName()} sudah masuk Buku Kontak dan berlangganan kategori ini.");
    }

    public function updateSubscription(Request $request, string $category, string $subscription): RedirectResponse
    {
        $model = $this->find($request, $category);
        $record = $model->subscriptions()->findOrFail($subscription);
        $data = $request->validate([
            'amount' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'status' => ['required', Rule::in(array_keys(InvoiceSubscription::STATUS_LABELS))],
        ]);

        $record->forceFill(['amount' => $data['amount'] ?? null, 'status' => $data['status']])->save();

        return back()->with('success', 'Langganan customer diperbarui. Berlaku untuk periode berikutnya.');
    }

    public function unsubscribe(Request $request, string $category, string $subscription): RedirectResponse
    {
        $this->find($request, $category)->subscriptions()->findOrFail($subscription)->delete();

        return back()->with('success', 'Customer dikeluarkan dari kategori. Invoice yang sudah dibuat tidak terpengaruh.');
    }

    private function subscribeCustomer(InvoiceCategory $category, string $customerId, ?int $amount): void
    {
        $subscription = new InvoiceSubscription();
        $subscription->forceFill(['category_id' => $category->getKey(), 'customer_id' => $customerId, 'amount' => $amount, 'status' => 'active'])->save();
    }

    private function validated(Request $request, InvoiceCategory $category): array
    {
        $request->merge([
            'prefix' => strtoupper(trim((string) $request->input('prefix'))),
            'reminder_days' => collect(preg_split('/[\s,;]+/', (string) $request->input('reminder_days'), -1, PREG_SPLIT_NO_EMPTY))->map(fn ($d) => is_numeric($d) ? (int) $d : $d)->unique()->values()->all(),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('invoice_categories', 'name')->where('branch_id', $category->branch_id)->ignore($category->getKey())],
            'description' => ['nullable', 'string', 'max:1000'],
            'prefix' => ['required', 'regex:/^[A-Z0-9]{2,10}$/'],
            'late_fee_mode' => ['required', Rule::in(array_keys(InvoiceCategory::LATE_FEE_MODES))],
            'late_fee_flat' => ['nullable', 'integer', 'min:0', 'max:100000000', 'required_if:late_fee_mode,flat,percent_flat'],
            'late_fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2', 'required_if:late_fee_mode,percent,percent_flat'],
            'late_fee_period' => ['required', Rule::in(['day', 'month'])],
            'late_fee_percent_days' => ['nullable', 'integer', 'min:1', 'max:365', 'required_if:late_fee_mode,percent_flat'],
            'email_enabled' => ['nullable', 'boolean'],
            'reminder_days' => ['array', 'max:5'],
            'reminder_days.*' => ['integer', 'min:0', 'max:60'],
            'status' => ['required', Rule::in(array_keys(InvoiceCategory::STATUS_LABELS))],
        ], ['name.unique' => 'Kategori dengan nama ini sudah ada.', 'prefix.regex' => 'Prefix 2–10 huruf besar/angka, mis. INV atau SPP.', 'reminder_days.*.integer' => 'Hari pengingat diisi angka, pisahkan dengan koma.'], [
            'late_fee_flat' => 'nominal denda', 'late_fee_percent' => 'persen denda', 'late_fee_percent_days' => 'batas hari persen', 'reminder_days' => 'hari pengingat',
        ]);

        return [...$data, 'email_enabled' => $request->boolean('email_enabled'), 'late_fee_flat' => (int) ($data['late_fee_flat'] ?? 0), 'late_fee_percent' => (float) ($data['late_fee_percent'] ?? 0)];
    }

    private function form(Request $request, InvoiceCategory $category, $branches): View
    {
        return view('invoice.categories.form', [...$this->shared($request, 'categories'), 'item' => $category, 'branches' => $branches]);
    }

    private function find(Request $request, string $id): InvoiceCategory
    {
        return InvoiceCategory::accessibleBy($request->user(), Menu::ABILITY_MANAGE)->findOrFail($id);
    }
}

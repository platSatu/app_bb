<?php

namespace App\Http\Controllers\Superadmin\Invoice;

use App\Http\Controllers\Controller;
use App\Models\InvoiceFeeRule;
use App\Models\PaymentGatewaySetting;
use App\Models\UserBranch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Superadmin › Invoice › Biaya per Customer (5 Nov 2026).
 * Customer tanpa aturan tetap memakai Biaya Invoice global. Lihat App\Services\Invoice\InvoiceFees.
 */
class InvoiceFeeRuleController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = (string) $request->query('branch');

        return view('superadmin.invoice.fee-rules', [
            'tab' => 'fee-rules',
            'isAdmin' => true,
            'rules' => InvoiceFeeRule::with('branch.company:id,name')
                ->when($branchId !== '', fn ($q) => $q->where('branch_id', $branchId))
                ->orderBy('branch_id')->orderByRaw('payment_method IS NULL DESC')->orderBy('payment_method')
                ->paginate(50)->withQueryString(),
            'branches' => UserBranch::with('company:id,name')->where('status', UserBranch::STATUS_ACTIVE)
                ->orderBy('name')->get(['id', 'name', 'company_id']),
            'methods' => PaymentGatewaySetting::duitku()->settlementMethods(),
            'branchId' => $branchId,
        ]);
    }

    /** Tambah / ubah aturan. Satu baris per customer + metode (metode kosong = semua metode). */
    public function store(Request $request): RedirectResponse
    {
        $methods = array_keys(PaymentGatewaySetting::duitku()->settlementMethods());

        $data = $request->validate([
            'branch_id' => ['required', 'uuid', Rule::exists('user_branches', 'id')],
            'payment_method' => ['nullable', 'string', Rule::in($methods)],
            'fee_type' => ['required', Rule::in([InvoiceFeeRule::TYPE_FLAT, InvoiceFeeRule::TYPE_PERCENT])],
            'fee_value' => ['required', 'numeric', 'min:0', $request->input('fee_type') === InvoiceFeeRule::TYPE_PERCENT ? 'max:50' : 'max:1000000', 'decimal:0,2'],
            'bearer' => ['required', Rule::in([InvoiceFeeRule::BEARER_MERCHANT, InvoiceFeeRule::BEARER_PAYER])],
        ], [], ['branch_id' => 'customer', 'payment_method' => 'metode', 'fee_type' => 'jenis biaya', 'fee_value' => 'nilai biaya', 'bearer' => 'ditanggung oleh']);

        $method = $data['payment_method'] ?? null;

        if ($data['bearer'] === InvoiceFeeRule::BEARER_PAYER && $method) {
            throw ValidationException::withMessages(['payment_method' => 'Biaya yang ditanggung pembayar hanya bisa untuk "Semua metode": metode baru dipilih pembayar di halaman Duitku, setelah total dikunci.']);
        }

        DB::transaction(function () use ($data, $method, $request) {
            // Kunci customer ini supaya dua admin tidak membuat aturan yang bertentangan bersamaan.
            UserBranch::whereKey($data['branch_id'])->lockForUpdate()->first();

            $others = InvoiceFeeRule::where('branch_id', $data['branch_id'])
                ->where(fn ($q) => $method ? $q->where('payment_method', '!=', $method)->orWhereNull('payment_method') : $q->whereNotNull('payment_method'))
                ->get();

            if ($data['bearer'] === InvoiceFeeRule::BEARER_PAYER && $others->isNotEmpty()) {
                throw ValidationException::withMessages(['bearer' => 'Customer ini sudah punya aturan per metode (ditanggung customer). Hapus dulu aturan itu sebelum memakai biaya yang ditanggung pembayar.']);
            }

            if ($data['bearer'] === InvoiceFeeRule::BEARER_MERCHANT && $others->contains('bearer', InvoiceFeeRule::BEARER_PAYER)) {
                throw ValidationException::withMessages(['bearer' => 'Customer ini memakai biaya yang ditanggung pembayar. Hapus dulu aturan itu sebelum menambah aturan per metode.']);
            }

            $rule = InvoiceFeeRule::where('branch_id', $data['branch_id'])
                ->when($method, fn ($q) => $q->where('payment_method', $method), fn ($q) => $q->whereNull('payment_method'))
                ->first() ?? new InvoiceFeeRule();

            $rule->forceFill([
                'branch_id' => $data['branch_id'],
                'payment_method' => $method,
                'fee_type' => $data['fee_type'],
                'fee_value' => $data['fee_value'],
                'bearer' => $data['bearer'],
                'updated_by' => $request->user()->getKey(),
            ])->save();
        });

        return redirect()->route('superadmin.invoices.settings.fee-rules.index', ['branch' => $data['branch_id']])
            ->with('success', 'Aturan biaya disimpan. Berlaku untuk pembayaran berikutnya.');
    }

    public function destroy(InvoiceFeeRule $rule): RedirectResponse
    {
        $branchId = $rule->branch_id;
        $rule->delete();

        return redirect()->route('superadmin.invoices.settings.fee-rules.index', ['branch' => $branchId])
            ->with('success', 'Aturan biaya dihapus.'.(InvoiceFeeRule::where('branch_id', $branchId)->exists() ? '' : ' Customer ini kembali memakai Biaya Invoice global.'));
    }
}

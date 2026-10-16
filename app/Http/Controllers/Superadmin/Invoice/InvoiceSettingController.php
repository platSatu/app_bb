<?php

namespace App\Http\Controllers\Superadmin\Invoice;

use App\Http\Controllers\Controller;
use App\Models\InvoiceSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Biaya layanan bizbos untuk setiap invoice yang dibayar, dipotong dari uang yang masuk ke owner. */
class InvoiceSettingController extends Controller
{
    public function edit(): View
    {
        return view('superadmin.invoice.setting', ['setting' => InvoiceSetting::current(), 'tab' => 'setting', 'routes' => 'superadmin.invoices', 'isAdmin' => true, 'canManage' => false]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fee_percent' => ['required', 'numeric', 'min:0', 'max:50', 'decimal:0,2'],
            'fee_flat' => ['required', 'integer', 'min:0', 'max:1000000'],
            'min_amount' => ['required', 'integer', 'min:10000', 'max:100000000'],
            'max_amount' => ['required', 'integer', 'gt:min_amount', 'max:1000000000'],
            'api_expiry_minutes' => ['required', 'integer', 'min:5', 'max:43200'],
        ], [], ['fee_percent' => 'biaya persen', 'fee_flat' => 'biaya flat', 'min_amount' => 'nominal minimal', 'max_amount' => 'nominal maksimal', 'api_expiry_minutes' => 'masa berlaku invoice API']);

        InvoiceSetting::current()->fill($data)->save();

        return back()->with('success', 'Pengaturan invoice disimpan. Biaya baru berlaku untuk pembayaran berikutnya.');
    }
}

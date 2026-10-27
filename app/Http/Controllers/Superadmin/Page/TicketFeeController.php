<?php

namespace App\Http\Controllers\Superadmin\Page;

use App\Http\Controllers\Controller;
use App\Models\TicketOrder;
use App\Models\TicketSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Persen biaya layanan tiket. Berlaku untuk checkout berikutnya; pesanan lama memakai persen yang terkunci. */
class TicketFeeController extends Controller
{
    public function edit(): View
    {
        $paid = TicketOrder::where('status', TicketOrder::STATUS_PAID);

        return view('superadmin.page.ticket-fee', [
            'setting' => TicketSetting::current(),
            'collected' => (int) (clone $paid)->sum('fee'),
            'sales' => (int) (clone $paid)->sum('subtotal'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'fee_percent' => ['required', 'numeric', 'min:0', 'max:'.TicketSetting::MAX_PERCENT, 'decimal:0,2'],
            'free_allowance' => ['required', 'integer', 'min:1', 'max:1000000'],
        ], [], ['fee_percent' => 'biaya layanan', 'free_allowance' => 'batas tiket event gratis']);

        TicketSetting::current()->fill($data)->save();

        return back()->with('success', 'Pengaturan tiket disimpan. Persen biaya berlaku untuk pesanan berikutnya; batas event gratis berlaku saat kategori disimpan.');
    }
}

<?php

namespace App\Http\Controllers\Superadmin\Voucher;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VoucherController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $vouchers = Voucher::query()
            ->with(['package:id,name', 'owner:id,name,email', 'redeemer:id,name,email'])
            ->status($request->query('status'))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('code', 'like', "%{$search}%")
                ->orWhereHas('owner', fn ($u) => $u->search($search))
                ->orWhereHas('redeemer', fn ($u) => $u->search($search))))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.voucher.index', compact('vouchers'));
    }
}

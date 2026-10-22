<?php

namespace App\Http\Controllers\Superadmin\Withdrawal;

use App\Http\Controllers\Controller;
use App\Models\Disbursement;
use App\Services\Withdrawal\DisbursementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** Pantau semua disbursement owner (dashboard & API), dan selesaikan transfer yang hasilnya belum pasti. */
class PayoutController extends Controller
{
    public function __construct(private readonly DisbursementService $service)
    {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $disbursements = Disbursement::query()
            ->with(['branch:id,name', 'user:id,name,email'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('reference', 'like', "%{$search}%")
                ->orWhere('account_name', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
            ->when($request->date('date_from'), fn ($q, $from) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($request->date('date_to'), fn ($q, $to) => $q->where('created_at', '<=', $to->endOfDay()))
            ->orderByRaw("CASE status WHEN 'needs_review' THEN 0 WHEN 'processing' THEN 1 ELSE 2 END")
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('superadmin.withdrawal.payouts', compact('disbursements'));
    }

    public function check(Disbursement $disbursement): RedirectResponse
    {
        return back()->with('info', "{$disbursement->reference}: ".$this->service->checkStatus($disbursement));
    }

    public function resolve(Request $request, Disbursement $disbursement): RedirectResponse
    {
        $data = $request->validate([
            'sent' => ['required', 'boolean'],
            'note' => ['required', 'string', 'max:300'],
        ], [], ['note' => 'catatan']);

        try {
            $this->service->resolve($disbursement, $request->user(), (bool) $data['sent'], $data['note']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $data['sent']
            ? "{$disbursement->reference} ditandai berhasil terkirim."
            : "{$disbursement->reference} ditandai tidak terkirim dan saldo owner sudah dikembalikan.");
    }
}

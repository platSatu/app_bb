<?php

namespace App\Http\Controllers\Superadmin\Deposit;

use App\Http\Controllers\Controller;
use App\Services\Deposit\MutationLedger;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Superadmin › Deposit › Mutasi: semua uang masuk (Kredit) & keluar (Debit) lewat Duitku bizbos. Hanya baca. */
class MutationController extends Controller
{
    public function index(Request $request, MutationLedger $ledger): View
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'source' => ['nullable', 'string', 'in:'.implode(',', array_keys(MutationLedger::SOURCES))],
            'direction' => ['nullable', 'in:credit,debit'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $filters = [
            'search' => $validated['search'] ?? null,
            'source' => $validated['source'] ?? null,
            'direction' => $validated['direction'] ?? null,
            'from' => $request->date('date_from')?->startOfDay(),
            'until' => $request->date('date_to')?->endOfDay(),
        ];

        return view('superadmin.deposit.mutations', [
            'rows' => $ledger->paginate($filters),
            'summary' => $ledger->summary($filters),
            'filters' => $filters,
        ]);
    }
}

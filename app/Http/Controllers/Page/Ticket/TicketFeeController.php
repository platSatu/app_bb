<?php

namespace App\Http\Controllers\Page\Ticket;

use App\Http\Controllers\Controller;
use App\Models\PageType;
use App\Models\TicketSetting;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Langkah sebelum membuat page tiket: simulasi pendapatan & penjelasan biaya layanan, lalu pilih penanggung biaya. */
class TicketFeeController extends Controller
{
    public function show(Request $request): View
    {
        $types = PageType::active()->where('template', PageType::TEMPLATE_TICKET)->orderBy('name')->get(['id', 'name']);
        abort_if($types->isEmpty(), 404);

        $setting = TicketSetting::current();

        return view('page.ticket.fee', [
            'type' => $types->firstWhere('id', $request->query('type')) ?? $types->first(),
            'percent' => $setting->fee_percent,
            'freeAllowance' => $setting->free_allowance,
            'freeUnit' => $setting->free_unit_price,
            'payer' => array_key_exists((string) $request->query('fee_payer'), TicketSetting::PAYER_LABELS) ? $request->query('fee_payer') : TicketSetting::PAYER_ORGANIZER,
        ]);
    }
}

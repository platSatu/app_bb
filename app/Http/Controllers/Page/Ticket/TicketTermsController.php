<?php

namespace App\Http\Controllers\Page\Ticket;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Syarat & Ketentuan per page tiket (settings.ticket.terms). Kosong = pembeli tidak perlu mencentang apa pun. */
class TicketTermsController extends Controller
{
    use ResolvesPage;

    public const MAX = 20000;

    public function edit(Request $request, string $page): View
    {
        return view('page.ticket.terms', [
            'page' => $this->templatePage($request, $page, PageType::TEMPLATE_TICKET),
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function update(Request $request, string $page): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_TICKET, Menu::ABILITY_MANAGE);
        $terms = trim((string) $request->validate(['terms' => ['nullable', 'string', 'max:'.self::MAX]],
            ['terms.max' => 'Syarat & Ketentuan maksimal '.number_format(self::MAX, 0, ',', '.').' karakter.'])['terms'] ?? '');

        // Dikunci supaya tidak saling menimpa dengan penyimpanan pengaturan lain (mis. desain e-ticket).
        DB::transaction(function () use ($model, $terms) {
            $locked = PageCategory::whereKey($model->getKey())->lockForUpdate()->firstOrFail();
            $locked->settings = array_replace_recursive($locked->settings ?? [], ['ticket' => ['terms' => $terms !== '' ? $terms : null]]);
            $locked->save();
        });

        return back()->with('success', $terms !== ''
            ? 'Syarat & Ketentuan disimpan. Pembeli wajib menyetujuinya sebelum membayar.'
            : 'Syarat & Ketentuan dikosongkan. Pembeli tidak perlu mencentang persetujuan.');
    }
}

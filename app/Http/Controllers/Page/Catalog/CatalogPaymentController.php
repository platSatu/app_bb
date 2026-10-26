<?php

namespace App\Http\Controllers\Page\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Page\Concerns\ResolvesPage;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\PaymentGatewaySetting;
use App\Services\Catalog\CatalogOrderService;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Tab Pembayaran katalog (settings.catalog.payment): transfer ke rekening owner dan/atau bayar online lewat bizbos. */
class CatalogPaymentController extends Controller
{
    use ResolvesPage;

    public function edit(Request $request, string $page): View
    {
        $gateway = PaymentGatewaySetting::duitku();

        return view('page.catalog.payment', [
            'page' => $this->templatePage($request, $page, PageType::TEMPLATE_CATALOG),
            'routes' => $this->routes($request),
            'canManage' => $this->canManage($request),
            'gatewayReady' => filled($gateway->merchantCode()) && filled($gateway->apiKey()),
        ]);
    }

    public function update(Request $request, string $page): RedirectResponse
    {
        $model = $this->templatePage($request, $page, PageType::TEMPLATE_CATALOG, Menu::ABILITY_MANAGE);

        // Baris rekening yang dikosongkan semua dibuang dulu, sisanya wajib lengkap.
        $request->merge(['banks' => array_values(array_filter((array) $request->input('banks'), fn ($bank) => is_array($bank) && array_filter(array_map('trim', array_map('strval', $bank)))))]);

        $data = $request->validate([
            'transfer' => ['boolean'],
            'online' => ['boolean'],
            'banks' => [$request->boolean('transfer') ? 'required' : 'nullable', 'array', 'max:'.CatalogOrderService::MAX_BANKS],
            'banks.*.bank' => ['required', 'string', 'max:50'],
            'banks.*.number' => ['required', 'string', 'regex:/^[0-9\s\-]{5,30}$/'],
            'banks.*.holder' => ['required', 'string', 'max:100'],
        ], [
            'banks.required' => 'Isi minimal 1 rekening tujuan transfer.',
            'banks.*.number.regex' => 'Nomor rekening hanya angka.',
        ], ['banks.*.bank' => 'nama bank', 'banks.*.number' => 'nomor rekening', 'banks.*.holder' => 'nama pemilik rekening']);

        $payment = [
            'transfer' => $request->boolean('transfer'),
            'online' => $request->boolean('online'),
            'banks' => array_map(fn ($bank) => [
                'bank' => trim($bank['bank']),
                'number' => preg_replace('/\D/', '', $bank['number']),
                'holder' => trim($bank['holder']),
            ], $data['banks'] ?? []),
        ];

        // Dikunci supaya tidak saling menimpa dengan penyimpanan pengaturan page lain. Daftar rekening diganti utuh.
        DB::transaction(function () use ($model, $payment) {
            $locked = PageCategory::whereKey($model->getKey())->lockForUpdate()->firstOrFail();
            $settings = $locked->settings ?? [];
            $settings['catalog']['payment'] = $payment;
            $locked->settings = $settings;
            $locked->save();
        });

        return back()->with('success', 'Pengaturan pembayaran disimpan.');
    }
}

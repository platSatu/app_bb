<?php

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Invoice\Concerns\InInvoiceArea;
use App\Models\InvoiceSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Dokumentasi integrasi API Invoice (sumbernya docs/invoice-api.md, satu tempat). */
class DocsController extends Controller
{
    use InInvoiceArea;

    public function __invoke(Request $request): View
    {
        $setting = InvoiceSetting::current();
        $markdown = strtr(file_get_contents(base_path('docs/invoice-api.md')), [
            '{{BASE_URL}}' => rtrim(url('/'), '/'),
            '{{MIN_AMOUNT}}' => number_format($setting->min_amount, 0, ',', '.'),
            '{{MAX_AMOUNT}}' => number_format($setting->max_amount, 0, ',', '.'),
            '{{EXPIRY}}' => (string) $setting->api_expiry_minutes,
        ]);

        return view('invoice.docs', [...$this->shared($request, 'docs'), 'html' => Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false])]);
    }
}

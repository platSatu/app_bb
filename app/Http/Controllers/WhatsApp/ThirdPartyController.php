<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsApp\Concerns\InWhatsAppArea;
use App\Models\WaDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * WhatsApp > Third Party > Integrasi Web: API Key per nomor untuk website/aplikasi lain, plus dokumentasinya.
 * Google Form ada di GoogleFormController.
 */
class ThirdPartyController extends Controller
{
    use InWhatsAppArea;

    public function index(Request $request): View|RedirectResponse
    {
        if (! $branch = $this->currentBranch($request)) {
            return $this->branchRequired();
        }

        return view('whatsapp.third-party.index', [
            'branches' => $this->ownerBranches($request),
            'branch' => $branch,
            'devices' => WaDevice::where('branch_id', $branch->id)->with('apiKey')->orderBy('name')->get(),
            'docs' => self::docsHtml(),
        ]);
    }

    /** Versi publik dokumentasi API (/docs/api-whatsapp), aman dibagikan ke developer. */
    public function publicDocs(): View
    {
        return view('whatsapp.third-party.docs-public', ['html' => self::docsHtml()]);
    }

    public static function docsHtml(): string
    {
        $markdown = strtr(file_get_contents(base_path('docs/whatsapp-api.md')), ['{{BASE_URL}}' => rtrim(url('/'), '/')]);

        return Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }
}

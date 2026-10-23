<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsApp\Concerns\InWhatsAppArea;
use App\Models\WaDevice;
use App\Models\WaFormIntegration;
use App\Models\WaTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * WhatsApp > Third Party: semua yang terhubung ke aplikasi lain dalam satu tempat.
 * API Key per nomor (kelola di halaman API Key device), integrasi Google Form, dan dokumentasi API.
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
            'forms' => WaFormIntegration::where('branch_id', $branch->id)->with('device:id,name,phone')->withCount([
                'submissions as sent_count' => fn ($q) => $q->where('status', 'sent'),
                'submissions as failed_count' => fn ($q) => $q->where('status', 'failed'),
            ])->latest()->get(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $branch = $this->currentBranch($request)) {
            return $this->branchRequired();
        }

        return view('whatsapp.third-party.form', [...$this->formData($branch->id), 'integration' => new WaFormIntegration(['is_active' => true]), 'branchId' => $branch->id]);
    }

    public function store(Request $request): RedirectResponse
    {
        $branch = $this->ownerBranches($request)->firstWhere('id', $request->input('branch_id'));
        abort_unless($branch, 404);

        $integration = new WaFormIntegration($this->validated($request, $branch->id));
        $integration->forceFill(['branch_id' => $branch->id, 'type' => 'google_form', 'webhook_token' => WaFormIntegration::newToken()])->save();

        return redirect()->route('whatsapp.third-party.forms.show', $integration)
            ->with('success', 'Google Form siap dihubungkan. Ikuti 3 langkah di bawah untuk memasang script-nya.');
    }

    public function show(Request $request, string $form): View
    {
        $integration = $this->integration($request, $form)->load('device:id,name,phone,status');

        return view('whatsapp.third-party.show', [
            'integration' => $integration,
            'submissions' => $integration->submissions()->latest('created_at')->paginate(15),
        ]);
    }

    public function edit(Request $request, string $form): View
    {
        $integration = $this->integration($request, $form);

        return view('whatsapp.third-party.form', [...$this->formData($integration->branch_id), 'integration' => $integration, 'branchId' => $integration->branch_id]);
    }

    public function update(Request $request, string $form): RedirectResponse
    {
        $integration = $this->integration($request, $form);
        $integration->fill($this->validated($request, $integration->branch_id))->save();

        return redirect()->route('whatsapp.third-party.forms.show', $integration)->with('success', 'Pengaturan Google Form diperbarui.');
    }

    public function destroy(Request $request, string $form): RedirectResponse
    {
        $integration = $this->integration($request, $form);
        $integration->delete();

        return redirect()->route('whatsapp.third-party.index', ['branch' => $integration->branch_id])
            ->with('success', "Integrasi {$integration->name} dihapus. Script di Google Form tidak lagi mengirim WhatsApp.");
    }

    public function regenerate(Request $request, string $form): RedirectResponse
    {
        $integration = $this->integration($request, $form);
        $integration->forceFill(['webhook_token' => WaFormIntegration::newToken()])->save();

        return back()->with('success', 'URL webhook baru dibuat. URL lama langsung tidak berlaku, salin ulang script ke Google Form ya.');
    }

    /** Dokumentasi API WhatsApp (sumber: docs/whatsapp-api.md). Versi publik ada di /docs/api-whatsapp. */
    public function docs(): View
    {
        return view('whatsapp.third-party.docs', ['html' => self::docsHtml()]);
    }

    public function publicDocs(): View
    {
        return view('whatsapp.third-party.docs-public', ['html' => self::docsHtml()]);
    }

    public static function docsHtml(): string
    {
        $markdown = strtr(file_get_contents(base_path('docs/whatsapp-api.md')), ['{{BASE_URL}}' => rtrim(url('/'), '/')]);

        return Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }

    private function integration(Request $request, string $id): WaFormIntegration
    {
        return WaFormIntegration::whereIn('branch_id', $this->ownerBranches($request)->pluck('id'))->findOrFail($id);
    }

    private function formData(string $branchId): array
    {
        return [
            'devices' => WaDevice::where('branch_id', $branchId)->orderBy('name')->get(['id', 'name', 'phone', 'status']),
            // Isi template bisa disalin ke pesan (teks saja).
            'templates' => WaTemplate::where('branch_id', $branchId)->where('is_active', true)->orderBy('name')->get()->map(fn ($t) => ['name' => $t->name, 'text' => $t->toText()]),
        ];
    }

    private function validated(Request $request, string $branchId): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'device_id' => ['required', Rule::exists('wa_devices', 'id')->where('branch_id', $branchId)],
            'phone_field' => ['required', 'string', 'max:191'],
            'message' => ['required', 'string', 'max:4000'],
        ], [], ['name' => 'nama integrasi', 'device_id' => 'nomor pengirim', 'phone_field' => 'judul pertanyaan nomor WhatsApp', 'message' => 'isi pesan']);

        return [...$data, 'phone_field' => trim($data['phone_field']), 'is_active' => $request->boolean('is_active')];
    }
}

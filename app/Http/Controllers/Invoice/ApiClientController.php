<?php

namespace App\Http\Controllers\Invoice;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Invoice\Concerns\InInvoiceArea;
use App\Jobs\SendInvoiceWebhook;
use App\Models\InvoiceApiClient;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

/** API key, webhook, dan origin popup per branch. Secret hanya ditampilkan sekali saat dibuat. */
class ApiClientController extends Controller
{
    use InInvoiceArea;

    public function index(Request $request): View|RedirectResponse
    {
        if (! ($branches = $this->ownerBranches($request))->count()) {
            return $this->branchRequired();
        }

        $branch = $branches->firstWhere('id', $request->query('branch')) ?? $branches->first();
        $client = InvoiceApiClient::accessibleBy($request->user())->where('branch_id', $branch->id)->first();

        return view('invoice.api.index', [
            ...$this->shared($request, 'api'),
            'branches' => $branches,
            'branch' => $branch,
            'client' => $client,
            'logs' => $client ? $client->webhookLogs()->with('invoice:id,number')->latest()->limit(20)->get() : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $branchId = $request->validate(['branch_id' => ['required', Rule::in($this->ownerBranches($request)->pluck('id'))]])['branch_id'];

        if (InvoiceApiClient::where('branch_id', $branchId)->exists()) {
            return back()->with('error', 'Branch ini sudah punya API key.');
        }

        $secret = InvoiceApiClient::newSecret('sk');
        $webhookSecret = InvoiceApiClient::newSecret('whsec');
        $client = new InvoiceApiClient();
        $client->forceFill([
            'branch_id' => $branchId,
            'api_key' => InvoiceApiClient::newApiKey(),
            'secret' => $secret,
            'webhook_secret' => $webhookSecret,
            'is_active' => true,
        ])->save();

        return redirect()->route('invoices.api.index', ['branch' => $branchId])
            ->with('secrets', ['secret' => $secret, 'webhook_secret' => $webhookSecret])
            ->with('success', 'API key berhasil dibuat. Salin Secret Key & Webhook Secret sekarang, keduanya hanya ditampilkan sekali.');
    }

    public function update(Request $request, string $client): RedirectResponse
    {
        $model = $this->find($request, $client);
        $request->merge(['allowed_origins' => collect(preg_split('/\s+/', (string) $request->input('allowed_origins'), -1, PREG_SPLIT_NO_EMPTY))->map(fn ($o) => rtrim($o, '/'))->unique()->values()->all()]);

        $data = $request->validate([
            'webhook_url' => ['nullable', 'url:'.(app()->isLocal() ? 'https,http' : 'https'), 'max:500'],
            'allowed_origins' => ['array', 'max:10'],
            'allowed_origins.*' => ['regex:#^https?://[a-z0-9.\-]+(:\d{1,5})?$#i'],
        ], ['allowed_origins.*.regex' => 'Origin ditulis lengkap tanpa path, mis. https://tokosaya.com atau http://localhost'], ['webhook_url' => 'URL webhook']);

        if (filled($data['webhook_url'] ?? null)) {
            try {
                SendInvoiceWebhook::resolveSafely($data['webhook_url']);
            } catch (Throwable $e) {
                return back()->withInput()->withErrors(['webhook_url' => $e->getMessage()]);
            }
        }

        $model->fill([...$data, 'is_active' => $request->boolean('is_active')])->save();

        return back()->with('success', 'Pengaturan API & webhook disimpan.');
    }

    public function regenerate(Request $request, string $client): RedirectResponse
    {
        $model = $this->find($request, $client);
        $which = $request->validate(['which' => ['required', Rule::in(['secret', 'webhook_secret'])]])['which'];
        $value = InvoiceApiClient::newSecret($which === 'secret' ? 'sk' : 'whsec');
        $model->forceFill([$which => $value])->save();

        return back()->with('secrets', [$which => $value])
            ->with('success', ($which === 'secret' ? 'Secret Key' : 'Webhook Secret').' baru dibuat. Yang lama langsung tidak berlaku, segera perbarui di website Anda.');
    }

    public function test(Request $request, string $client): RedirectResponse
    {
        $model = $this->find($request, $client);

        if (! $model->webhook_url) {
            return back()->with('error', 'Isi URL webhook dulu, lalu simpan.');
        }

        try {
            dispatch_sync(new SendInvoiceWebhook($model->getKey(), null, 'ping'));
        } catch (Throwable $e) {
            return back()->with('error', 'Webhook test gagal: '.Str::after($e->getMessage(), 'Webhook gagal: '));
        }

        return back()->with('success', 'Webhook test berhasil diterima server Anda (event "ping").');
    }

    private function find(Request $request, string $id): InvoiceApiClient
    {
        return InvoiceApiClient::accessibleBy($request->user(), Menu::ABILITY_MANAGE)->findOrFail($id);
    }
}

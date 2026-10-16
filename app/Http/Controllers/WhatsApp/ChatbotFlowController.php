<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsApp\Concerns\InWhatsAppArea;
use App\Models\WaChatbotFlow;
use App\Models\WaDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Settings > Chatbot Flow: menu bertingkat yang dimulai dari kata kunci. */
class ChatbotFlowController extends Controller
{
    use InWhatsAppArea;

    private const MAX_NODES = 30;
    private const MAX_OPTIONS = 10;

    public function index(Request $request): View|RedirectResponse
    {
        if (! $branch = $this->currentBranch($request)) {
            return $this->branchRequired();
        }

        return view('whatsapp.flows.index', [
            'branches' => $this->ownerBranches($request),
            'branch' => $branch,
            'flows' => WaChatbotFlow::where('branch_id', $branch->id)->with('device:id,name')->orderBy('name')->get(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $branch = $this->currentBranch($request)) {
            return $this->branchRequired();
        }

        return $this->form($branch->id, new WaChatbotFlow(['match_type' => 'exact', 'timeout_minutes' => 30, 'is_active' => true, 'nodes' => [
            ['id' => 'start', 'message' => 'Halo {nama}, terima kasih sudah menghubungi kami. Ada yang bisa dibantu?', 'options' => [
                ['key' => '1', 'label' => 'Info layanan', 'action' => 'end', 'next' => null],
                ['key' => '2', 'label' => 'Bicara dengan admin', 'action' => 'handover', 'next' => null],
            ]],
        ]]));
    }

    public function edit(Request $request, string $flow): View
    {
        $model = $this->flow($request, $flow);

        return $this->form($model->branch_id, $model);
    }

    public function store(Request $request): RedirectResponse
    {
        $branchId = $request->validate(['branch_id' => ['required', Rule::in($this->ownerBranches($request)->pluck('id'))]])['branch_id'];
        $flow = new WaChatbotFlow();
        $flow->forceFill(['branch_id' => $branchId] + $this->validated($request, $branchId))->save();

        return redirect()->route('whatsapp.flows.index', ['branch' => $branchId])->with('success', 'Chatbot flow disimpan.');
    }

    public function update(Request $request, string $flow): RedirectResponse
    {
        $model = $this->flow($request, $flow);
        $model->forceFill($this->validated($request, $model->branch_id))->save();

        return redirect()->route('whatsapp.flows.index', ['branch' => $model->branch_id])->with('success', 'Chatbot flow diperbarui.');
    }

    public function destroy(Request $request, string $flow): RedirectResponse
    {
        $this->flow($request, $flow)->delete();

        return back()->with('success', 'Chatbot flow dihapus.');
    }

    private function form(string $branchId, WaChatbotFlow $flow): View
    {
        return view('whatsapp.flows.form', [
            'flow' => $flow,
            'branchId' => $branchId,
            'devices' => WaDevice::where('branch_id', $branchId)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function flow(Request $request, string $id): WaChatbotFlow
    {
        return WaChatbotFlow::whereIn('branch_id', $this->ownerBranches($request)->pluck('id'))->findOrFail($id);
    }

    private function validated(Request $request, string $branchId): array
    {
        $request->merge(['keywords' => $this->keywordList($request->input('keywords'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'device_id' => ['nullable', Rule::exists('wa_devices', 'id')->where('branch_id', $branchId)],
            'match_type' => ['required', Rule::in(array_keys(WaChatbotFlow::MATCH_TYPES))],
            'keywords' => ['required', 'array', 'max:20'],
            'keywords.*' => ['string', 'max:50'],
            'end_message' => ['nullable', 'string', 'max:1000'],
            'handover_message' => ['nullable', 'string', 'max:1000'],
            'timeout_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'nodes' => ['required', 'string', 'max:200000'],
        ], ['keywords.required' => 'Isi minimal satu kata kunci pembuka, mis. menu.'], ['name' => 'nama flow', 'timeout_minutes' => 'batas waktu sesi']);

        return ['nodes' => $this->nodes($data['nodes']), 'is_active' => $request->boolean('is_active')] + $data;
    }

    /** Bersihkan & periksa struktur node dari editor. */
    private function nodes(string $json): array
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['nodes' => $message]);
        $raw = json_decode($json, true);
        if (! is_array($raw) || ! $raw || count($raw) > self::MAX_NODES) {
            $fail('Flow harus punya 1 sampai '.self::MAX_NODES.' langkah.');
        }

        $nodes = [];
        foreach ($raw as $node) {
            $id = (string) ($node['id'] ?? '');
            $message = trim((string) ($node['message'] ?? ''));
            if (! preg_match('/^[a-z0-9_-]{1,40}$/', $id) || isset($nodes[$id])) {
                $fail('Kode langkah tidak valid atau kembar.');
            }
            if ($message === '' || mb_strlen($message) > 2000) {
                $fail("Pesan di langkah \"{$id}\" wajib diisi (maks 2000 karakter).");
            }

            $options = [];
            foreach (array_slice((array) ($node['options'] ?? []), 0, self::MAX_OPTIONS) as $option) {
                $key = trim((string) ($option['key'] ?? ''));
                $label = trim((string) ($option['label'] ?? ''));
                $action = in_array($option['action'] ?? '', ['next', 'end', 'handover'], true) ? $option['action'] : 'end';
                if ($key === '' || $label === '' || mb_strlen($key) > 10 || mb_strlen($label) > 100) {
                    $fail("Pilihan di langkah \"{$id}\" butuh nomor (maks 10 karakter) dan teks (maks 100 karakter).");
                }
                $options[] = ['key' => $key, 'label' => $label, 'action' => $action, 'next' => $action === 'next' ? (string) ($option['next'] ?? '') : null];
            }
            $nodes[$id] = ['id' => $id, 'message' => $message, 'options' => $options];
        }

        if (! isset($nodes['start'])) {
            $fail('Flow wajib punya langkah pembuka dengan kode "start".');
        }
        foreach ($nodes as $node) {
            foreach ($node['options'] as $option) {
                if ($option['action'] === 'next' && ! isset($nodes[$option['next']])) {
                    $fail("Pilihan \"{$option['label']}\" menuju langkah yang tidak ada.");
                }
            }
        }

        return array_values($nodes);
    }
}

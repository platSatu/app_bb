<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsApp\Concerns\InWhatsAppArea;
use App\Models\WaAutoReply;
use App\Models\WaDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Settings > Auto Reply: balasan otomatis berdasarkan kata kunci. */
class AutoReplyController extends Controller
{
    use InWhatsAppArea;

    public function index(Request $request): View|RedirectResponse
    {
        if (! $branch = $this->currentBranch($request)) {
            return $this->branchRequired();
        }

        return view('whatsapp.auto-replies.index', [
            'branches' => $this->ownerBranches($request),
            'branch' => $branch,
            'devices' => WaDevice::where('branch_id', $branch->id)->orderBy('name')->get(['id', 'name']),
            'rules' => WaAutoReply::where('branch_id', $branch->id)->with('device:id,name')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $branchId = $request->validate(['branch_id' => ['required', Rule::in($this->ownerBranches($request)->pluck('id'))]])['branch_id'];
        $rule = new WaAutoReply();
        $rule->forceFill(['branch_id' => $branchId] + $this->validated($request, $branchId))->save();

        return back()->with('success', 'Auto reply dibuat.');
    }

    public function update(Request $request, string $rule): RedirectResponse
    {
        $model = $this->rule($request, $rule);
        $model->forceFill($this->validated($request, $model->branch_id))->save();

        return back()->with('success', 'Auto reply diperbarui.');
    }

    public function destroy(Request $request, string $rule): RedirectResponse
    {
        $this->rule($request, $rule)->delete();

        return back()->with('success', 'Auto reply dihapus.');
    }

    private function rule(Request $request, string $id): WaAutoReply
    {
        return WaAutoReply::whereIn('branch_id', $this->ownerBranches($request)->pluck('id'))->findOrFail($id);
    }

    private function validated(Request $request, string $branchId): array
    {
        $request->merge(['keywords' => $this->keywordList($request->input('keywords'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'device_id' => ['nullable', Rule::exists('wa_devices', 'id')->where('branch_id', $branchId)],
            'match_type' => ['required', Rule::in(array_keys(WaAutoReply::MATCH_TYPES))],
            'keywords' => ['array', 'max:30', Rule::requiredIf($request->input('match_type') !== 'any')],
            'keywords.*' => ['string', 'max:100'],
            'body' => ['required', 'string', 'max:4000'],
            'cooldown_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
        ], ['keywords.required' => 'Isi minimal satu kata kunci.'], ['name' => 'nama', 'body' => 'isi balasan', 'cooldown_minutes' => 'jeda ulang']);

        return $data + ['is_active' => $request->boolean('is_active'), 'device_id' => null];
    }
}

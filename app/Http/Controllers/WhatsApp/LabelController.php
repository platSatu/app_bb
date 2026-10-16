<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsApp\Concerns\InWhatsAppArea;
use App\Models\WaLabel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Settings > Label: penanda chat di inbox, per branch. */
class LabelController extends Controller
{
    use InWhatsAppArea;

    public function index(Request $request): View|RedirectResponse
    {
        if (! $branch = $this->currentBranch($request)) {
            return $this->branchRequired();
        }

        return view('whatsapp.labels.index', [
            'branches' => $this->ownerBranches($request),
            'branch' => $branch,
            'labels' => WaLabel::where('branch_id', $branch->id)->withCount('chats')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $branchId = $request->validate(['branch_id' => ['required', Rule::in($this->ownerBranches($request)->pluck('id'))]])['branch_id'];
        $label = new WaLabel($request->validate($this->rules($branchId), $this->messages()));
        $label->forceFill(['branch_id' => $branchId])->save();

        return back()->with('success', 'Label dibuat.');
    }

    public function update(Request $request, string $label): RedirectResponse
    {
        $model = $this->label($request, $label);
        $model->update($request->validate($this->rules($model->branch_id, $model->id), $this->messages()));

        return back()->with('success', 'Label diperbarui.');
    }

    public function destroy(Request $request, string $label): RedirectResponse
    {
        $this->label($request, $label)->delete();

        return back()->with('success', 'Label dihapus dan dilepas dari semua chat.');
    }

    private function label(Request $request, string $id): WaLabel
    {
        return WaLabel::whereIn('branch_id', $this->ownerBranches($request)->pluck('id'))->findOrFail($id);
    }

    private function rules(string $branchId, ?string $ignore = null): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('wa_labels')->where('branch_id', $branchId)->ignore($ignore)],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    private function messages(): array
    {
        return ['name.unique' => 'Nama label ini sudah ada.', 'color.regex' => 'Warna tidak valid.'];
    }
}

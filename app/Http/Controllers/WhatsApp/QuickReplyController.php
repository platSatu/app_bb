<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsApp\Concerns\InWhatsAppArea;
use App\Models\WaQuickReply;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Settings > Quick Reply: teks siap pakai di inbox (ketik / di kotak pesan). */
class QuickReplyController extends Controller
{
    use InWhatsAppArea;

    public function index(Request $request): View|RedirectResponse
    {
        if (! $branch = $this->currentBranch($request)) {
            return $this->branchRequired();
        }

        return view('whatsapp.quick-replies.index', [
            'branches' => $this->ownerBranches($request),
            'branch' => $branch,
            'replies' => WaQuickReply::where('branch_id', $branch->id)->orderBy('title')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $branchId = $request->validate(['branch_id' => ['required', Rule::in($this->ownerBranches($request)->pluck('id'))]])['branch_id'];
        $reply = new WaQuickReply($this->validated($request, $branchId));
        $reply->forceFill(['branch_id' => $branchId])->save();

        return back()->with('success', 'Quick reply dibuat.');
    }

    public function update(Request $request, string $reply): RedirectResponse
    {
        $model = $this->reply($request, $reply);
        $model->update($this->validated($request, $model->branch_id, $model->id));

        return back()->with('success', 'Quick reply diperbarui.');
    }

    public function destroy(Request $request, string $reply): RedirectResponse
    {
        $this->reply($request, $reply)->delete();

        return back()->with('success', 'Quick reply dihapus.');
    }

    private function reply(Request $request, string $id): WaQuickReply
    {
        return WaQuickReply::whereIn('branch_id', $this->ownerBranches($request)->pluck('id'))->findOrFail($id);
    }

    private function validated(Request $request, string $branchId, ?string $ignore = null): array
    {
        $request->merge(['shortcut' => $request->filled('shortcut') ? strtolower(ltrim(trim($request->input('shortcut')), '/')) : null]);

        return $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'shortcut' => ['nullable', 'string', 'max:30', 'regex:/^[a-z0-9_-]+$/', Rule::unique('wa_quick_replies')->where('branch_id', $branchId)->ignore($ignore)],
            'body' => ['required', 'string', 'max:4000'],
        ], ['shortcut.regex' => 'Shortcut hanya huruf kecil, angka, - atau _.', 'shortcut.unique' => 'Shortcut ini sudah dipakai.'], ['title' => 'judul', 'body' => 'isi pesan']);
    }
}

<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsApp\Concerns\InWhatsAppArea;
use App\Models\ContactGroup;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Phone Book > Group: kelompok kontak per branch untuk blast & pesan terjadwal. */
class ContactGroupController extends Controller
{
    use InWhatsAppArea;

    public function index(Request $request): View|RedirectResponse
    {
        if (! $branch = $this->currentBranch($request)) {
            return $this->branchRequired();
        }

        return view('whatsapp.groups.index', [
            'branches' => $this->ownerBranches($request),
            'branch' => $branch,
            'groups' => ContactGroup::where('branch_id', $branch->id)->withCount('customers')->orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, string $group): View
    {
        $model = $this->group($request, $group);

        return view('whatsapp.groups.show', [
            'group' => $model->load('branch:id,name'),
            'members' => $model->customers()->search($request->query('search'))->orderBy('name')->paginate(25)->withQueryString(),
            'available' => Customer::where('branch_id', $model->branch_id)->whereDoesntHave('groups', fn ($q) => $q->whereKey($model->id))
                ->orderBy('name')->limit(500)->get(['id', 'name', 'phone', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', Rule::in($this->ownerBranches($request)->pluck('id'))],
            ...$this->rules($request->input('branch_id')),
        ], $this->messages());

        $group = new ContactGroup($data);
        $group->forceFill(['branch_id' => $data['branch_id']])->save();

        return redirect()->route('whatsapp.groups.show', $group)->with('success', 'Group dibuat. Sekarang tambahkan anggotanya.');
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        $model = $this->group($request, $group);
        $model->update($request->validate($this->rules($model->branch_id, $model->id), $this->messages()));

        return back()->with('success', 'Group diperbarui.');
    }

    public function destroy(Request $request, string $group): RedirectResponse
    {
        $model = $this->group($request, $group);
        $model->delete();

        return redirect()->route('whatsapp.groups.index', ['branch' => $model->branch_id])->with('success', 'Group dihapus. Kontaknya tetap ada di Buku Kontak.');
    }

    public function addMembers(Request $request, string $group): RedirectResponse
    {
        $model = $this->group($request, $group);
        $ids = $request->validate([
            'customer_ids' => ['required', 'array', 'max:500'],
            'customer_ids.*' => [Rule::exists('customers', 'id')->where('branch_id', $model->branch_id)],
        ], ['customer_ids.required' => 'Pilih minimal satu kontak.'])['customer_ids'];

        $model->customers()->syncWithoutDetaching(array_fill_keys($ids, ['created_at' => now()]));

        return back()->with('success', count($ids).' kontak ditambahkan ke group.');
    }

    public function removeMember(Request $request, string $group, string $customer): RedirectResponse
    {
        $this->group($request, $group)->customers()->detach($customer);

        return back()->with('success', 'Kontak dikeluarkan dari group.');
    }

    private function group(Request $request, string $id): ContactGroup
    {
        return ContactGroup::whereIn('branch_id', $this->ownerBranches($request)->pluck('id'))->findOrFail($id);
    }

    private function rules(?string $branchId, ?string $ignore = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('contact_groups')->where('branch_id', $branchId)->ignore($ignore)],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function messages(): array
    {
        return ['name.unique' => 'Nama group ini sudah dipakai di branch tersebut.'];
    }
}

<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsApp\Concerns\InWhatsAppArea;
use App\Models\ContactGroup;
use App\Models\Customer;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\Contact\ContactBookService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Phone Book > Contact: Buku Kontak bizbos per branch, bisa tambah, ubah, atur group, dan impor CSV. */
class ContactController extends Controller
{
    use InWhatsAppArea;

    public function index(Request $request): View|RedirectResponse
    {
        if (! $branch = $this->currentBranch($request)) {
            return $this->branchRequired();
        }

        $groups = ContactGroup::where('branch_id', $branch->id)->orderBy('name')->get(['id', 'name']);
        $customers = Customer::where('branch_id', $branch->id)
            ->with('groups:id,name')
            ->search($request->query('search'))
            ->when($request->query('group'), fn ($q, $id) => $q->whereHas('groups', fn ($g) => $g->whereKey($id)))
            ->latest('last_seen_at')
            ->paginate(25)
            ->withQueryString();

        return view('whatsapp.contacts.index', [
            'branches' => $this->ownerBranches($request),
            'branch' => $branch,
            'groups' => $groups,
            'customers' => $customers,
        ]);
    }

    public function store(Request $request, ContactBookService $book): RedirectResponse
    {
        $branch = $this->branchFromInput($request);
        $data = $this->validated($request, $branch);

        $book->addMany($branch, [$data], null, 'manual');
        Customer::where('branch_id', $branch->id)->where('phone', $data['phone'])->firstOrFail()
            ->groups()->syncWithPivotValues($data['group_ids'] ?? [], ['created_at' => now()]);

        return back()->with('success', 'Kontak ditambahkan.');
    }

    public function update(Request $request, string $customer): RedirectResponse
    {
        $model = Customer::whereIn('branch_id', $this->ownerBranches($request)->pluck('id'))->findOrFail($customer);
        $branch = $this->ownerBranches($request)->firstWhere('id', $model->branch_id);
        $data = $this->validated($request, $branch, $model);

        $model->forceFill(['name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email'] ?? null])->save();
        $model->groups()->syncWithPivotValues($data['group_ids'] ?? [], ['created_at' => now()]);

        return back()->with('success', 'Kontak diperbarui.');
    }

    public function import(Request $request, ContactBookService $book): RedirectResponse
    {
        $branch = $this->branchFromInput($request);
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
            'group_id' => ['nullable', Rule::exists('contact_groups', 'id')->where('branch_id', $branch->id)],
        ], ['file.mimes' => 'File harus berformat CSV.', 'file.max' => 'Ukuran file maksimal 2 MB.']);

        [$rows, $valid] = $book->readCsv($request->file('file'));
        if (! $valid) {
            return back()->with('error', 'Kolom WhatsApp/email tidak ditemukan. Baris pertama CSV harus berisi judul kolom, mis. nama,whatsapp,email.');
        }

        $result = $book->addMany($branch, $rows, ! empty($data['group_id']) ? ContactGroup::find($data['group_id']) : null, 'import');

        return back()->with('success', "Impor selesai: {$result['saved']} kontak tersimpan".($result['skipped'] ? ", {$result['skipped']} baris dilewati karena tanpa nomor/email." : '.'));
    }

    private function branchFromInput(Request $request): UserBranch
    {
        $branch = $this->ownerBranches($request)->firstWhere('id', $request->input('branch_id'));
        abort_unless($branch, 404);

        return $branch;
    }

    private function validated(Request $request, UserBranch $branch, ?Customer $self = null): array
    {
        $request->merge(['phone' => User::normalizePhone($request->input('phone')) ?: null, 'email' => $request->filled('email') ? strtolower(trim($request->input('email'))) : null]);

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'min:10', 'max:20', Rule::unique('customers')->where('branch_id', $branch->id)->ignore($self?->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('customers')->where('branch_id', $branch->id)->ignore($self?->id)],
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => [Rule::exists('contact_groups', 'id')->where('branch_id', $branch->id)],
        ], ['phone.unique' => 'Nomor WhatsApp ini sudah ada di Buku Kontak.', 'email.unique' => 'Email ini sudah dipakai kontak lain.'], ['phone' => 'nomor WhatsApp']);
    }
}

<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Http\Controllers\WhatsApp\Concerns\InWhatsAppArea;
use App\Models\ContactGroup;
use App\Models\Customer;
use App\Models\UserBranch;
use App\Services\Contact\ContactBookService;
use App\Services\Customer\CustomerAccount;
use App\Services\Customer\CustomerService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Phone Book > Customers: customer WhatsApp per branch, bisa tambah, ubah, hapus, atur group, impor Excel/CSV, dan dijadikan user. */
class ContactController extends Controller
{
    use InWhatsAppArea;

    public function index(Request $request): View|RedirectResponse
    {
        if (! $branch = $this->currentBranch($request)) {
            return $this->branchRequired();
        }

        $groups = ContactGroup::where('branch_id', $branch->id)->orderBy('name')->get(['id', 'name']);
        $customers = Customer::where('branch_id', $branch->id)->forApp('whatsapp')
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
            'accounts' => CustomerAccount::emailsWithAccount($customers->items()),
        ]);
    }

    public function store(Request $request, CustomerService $customers): RedirectResponse
    {
        $branch = $this->branchFromInput($request);
        $data = $this->validated($request, $branch);

        DB::transaction(function () use ($branch, $data, $customers) {
            $locked = UserBranch::whereKey($branch->id)->lockForUpdate()->firstOrFail();
            $customers->resolve($locked, 'whatsapp', $data['name'], $data['phone'], $data['email'], 'manual')
                ->groups()->syncWithPivotValues($data['group_ids'] ?? [], ['created_at' => now()]);
        });

        return back()->with('success', 'Kontak ditambahkan.');
    }

    public function update(Request $request, string $customer): RedirectResponse
    {
        $model = $this->owned($request, $customer);
        $data = $this->validated($request, $this->ownerBranches($request)->firstWhere('id', $model->branch_id), $model);

        $model->forceFill(['name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email']])->save();
        $model->groups()->syncWithPivotValues($data['group_ids'] ?? [], ['created_at' => now()]);

        return back()->with('success', 'Kontak diperbarui.');
    }

    public function destroy(Request $request, string $customer): RedirectResponse
    {
        $model = $this->owned($request, $customer);
        $model->delete();

        return back()->with('success', "{$model->displayName()} dihapus dari Phone Book.");
    }

    public function account(Request $request, string $customer, CustomerAccount $accounts): RedirectResponse
    {
        try {
            [$user, $created] = $accounts->grant($this->owned($request, $customer));
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($created ? 'success' : 'info', $created
            ? "Akun login untuk {$user->email} sudah dibuat. Link untuk membuat password dikirim ke email tersebut."
            : "{$user->email} sudah punya akun, jadi bisa langsung login.");
    }

    public function import(Request $request, ContactBookService $book): RedirectResponse
    {
        $branch = $this->branchFromInput($request);
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
            'group_id' => ['nullable', Rule::exists('contact_groups', 'id')->where('branch_id', $branch->id)],
        ], ['file.mimes' => 'File harus Excel (.xlsx/.xls) atau CSV.', 'file.max' => 'Ukuran file maksimal 5 MB.']);

        [$rows, $valid] = $book->read($request->file('file'));
        if (! $valid) {
            return back()->with('error', 'Kolom WhatsApp/email tidak ditemukan. Baris pertama harus berisi judul kolom: nama, whatsapp, email. Pakai file contoh supaya pas.');
        }

        $result = $book->addMany($branch, $rows, ! empty($data['group_id']) ? ContactGroup::find($data['group_id']) : null, 'import');

        return back()->with('success', "Impor selesai: {$result['saved']} kontak tersimpan".($result['skipped'] ? ", {$result['skipped']} baris dilewati karena tanpa nomor/email." : '.'));
    }

    public function template(ContactBookService $book): StreamedResponse
    {
        return $book->template('contoh-customers-whatsapp.xlsx');
    }

    private function owned(Request $request, string $id): Customer
    {
        return Customer::whereIn('branch_id', $this->ownerBranches($request)->pluck('id'))->forApp('whatsapp')->findOrFail($id);
    }

    private function branchFromInput(Request $request): UserBranch
    {
        $branch = $this->ownerBranches($request)->firstWhere('id', $request->input('branch_id'));
        abort_unless($branch, 404);

        return $branch;
    }

    private function validated(Request $request, UserBranch $branch, ?Customer $self = null): array
    {
        return CustomerService::validate($request, $branch->id, 'whatsapp', $self, [
            'group_ids' => ['nullable', 'array'],
            'group_ids.*' => [Rule::exists('contact_groups', 'id')->where('branch_id', $branch->id)],
        ]);
    }
}

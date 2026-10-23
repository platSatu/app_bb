<?php

namespace App\Http\Controllers\Contact;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\UserBranch;
use App\Services\Contact\ContactBookService;
use App\Services\Customer\CustomerAccount;
use App\Services\Customer\CustomerService;
use App\Services\Subscription\AppAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Customers per aplikasi: Pages (/pages/customers), Invoice (/invoices/customers),
 * dan superadmin (/superadmin/contacts, semua aplikasi, hanya lihat). Customers WhatsApp ada di Phone Book.
 */
class CustomerController extends Controller
{
    private const ROUTE_APPS = ['pages.customers' => 'pages', 'invoices.customers' => 'invoices'];

    public function index(Request $request): View
    {
        $user = $request->user();
        [$base, $app] = $this->context($request);
        $filterApp = $app ?? (array_key_exists((string) $request->query('app'), Customer::APPS) ? $request->query('app') : null);

        $customers = Customer::accessibleBy($user)
            ->with(['branch:id,name', 'company:id,name'])
            ->when($filterApp, fn ($q, $a) => $q->forApp($a))
            ->when($app === 'pages', fn ($q) => $q->withCount('submissions'))
            ->when($app === 'invoices', fn ($q) => $q->withCount('invoices'))
            ->search($request->query('search'))
            ->when($request->query('branch'), fn ($q, $id) => $q->where('branch_id', $id))
            ->latest('last_seen_at')
            ->paginate(20)
            ->withQueryString();

        return view('contact.customer.index', [
            'customers' => $customers,
            'app' => $app,
            // Superadmin memfilter lewat pencarian; owner memilih dari branch miliknya.
            'branches' => $user->isPlatformTeam() ? collect() : UserBranch::where('user_id', $user->getKey())->orderBy('name')->get(['id', 'name']),
            // Branch yang boleh ditambah customer: paket aplikasinya aktif.
            'writable' => $app ? $this->writableBranches($request, $app) : collect(),
            'accounts' => CustomerAccount::emailsWithAccount($customers->items()),
            'base' => $base,
        ]);
    }

    public function show(Request $request, string $customer): View
    {
        [$base, $app] = $this->context($request);
        $model = Customer::accessibleBy($request->user())->when($app, fn ($q, $a) => $q->forApp($a))
            ->with(['branch:id,name', 'company:id,name'])->findOrFail($customer);
        $admin = $base === 'superadmin.contacts';

        return view('contact.customer.show', [
            'customer' => $model,
            'base' => $base,
            'pagesRoute' => $admin ? 'superadmin.pages' : 'pages',
            'invoiceRoute' => $admin ? null : 'invoices.show',
            'submissions' => $model->app_key === 'pages' ? $model->submissions()->with(['page:id,name', 'ticketOrder:id,submission_id'])->latest()->paginate(15) : null,
            'invoices' => $model->app_key === 'invoices' ? $model->invoices()->latest()->paginate(15) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [, $app] = $this->context($request);
        $branch = $this->writableBranch($request, $app);
        $data = CustomerService::validate($request, $branch->id, $app);

        $customer = new Customer();
        $customer->forceFill([
            'company_id' => $branch->company_id, 'branch_id' => $branch->id, 'app_key' => $app,
            'name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email'],
            'source' => 'manual', 'first_seen_at' => now(), 'last_seen_at' => now(),
        ])->save();

        return back()->with('success', "{$customer->displayName()} ditambahkan ke Customers.");
    }

    public function update(Request $request, string $customer): RedirectResponse
    {
        $model = $this->owned($request, $customer);
        $data = CustomerService::validate($request, $model->branch_id, $model->app_key, $model);
        $model->forceFill(['name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email']])->save();

        return back()->with('success', 'Data customer diperbarui.');
    }

    public function destroy(Request $request, string $customer): RedirectResponse
    {
        $model = $this->owned($request, $customer);
        $model->delete();

        return back()->with('success', "{$model->displayName()} dihapus dari Customers. Riwayat transaksi/invoice tetap tersimpan.");
    }

    public function import(Request $request, ContactBookService $book): RedirectResponse
    {
        [, $app] = $this->context($request);
        $branch = $this->writableBranch($request, $app);
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120']], ['file.mimes' => 'File harus Excel (.xlsx/.xls) atau CSV.', 'file.max' => 'Ukuran file maksimal 5 MB.']);

        [$rows, $valid] = $book->read($request->file('file'));
        if (! $valid) {
            return back()->with('error', 'Kolom WhatsApp/email tidak ditemukan. Baris pertama harus berisi judul kolom: nama, whatsapp, email. Pakai file contoh supaya pas.');
        }

        $result = $book->addMany($branch, $rows, null, 'import', $app);

        return back()->with('success', "{$result['saved']} customer diimpor".($result['skipped'] ? ", {$result['skipped']} baris dilewati karena tanpa WhatsApp dan email." : '.'));
    }

    public function template(Request $request, ContactBookService $book): StreamedResponse
    {
        [, $app] = $this->context($request);

        return $book->template("contoh-customers-{$app}.xlsx");
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

    /** @return array{0: string, 1: ?string} [nama route dasar, app_key; null = semua (superadmin)] */
    private function context(Request $request): array
    {
        $base = (string) str($request->route()?->getName())->beforeLast('.');

        return [$base, self::ROUTE_APPS[$base] ?? null];
    }

    private function writableBranches(Request $request, string $app)
    {
        return UserBranch::whereIn('id', app(AppAccess::class)->ownerBranchIds($request->user(), $app))->orderBy('name')->get(['id', 'name', 'company_id']);
    }

    private function writableBranch(Request $request, string $app): UserBranch
    {
        $branches = $this->writableBranches($request, $app);
        $id = $request->validate(['branch_id' => ['required', Rule::in($branches->modelKeys())]], ['branch_id.in' => 'Pilih branch yang paketnya aktif.'])['branch_id'];

        return $branches->firstWhere('id', $id);
    }

    /** Customer aplikasi ini di branch milik owner yang login. */
    private function owned(Request $request, string $id): Customer
    {
        [, $app] = $this->context($request);

        return Customer::forApp($app)->whereIn('branch_id', UserBranch::where('user_id', $request->user()->getKey())->select('id'))->findOrFail($id);
    }
}

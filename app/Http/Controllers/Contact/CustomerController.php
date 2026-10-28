<?php

namespace App\Http\Controllers\Contact;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\UserBranch;
use App\Services\Contact\ContactBookService;
use App\Services\Customer\CustomerAccount;
use App\Services\Customer\CustomerService;
use App\Services\Subscription\AppAccess;
use App\Support\Apps;
use App\Support\XlsxExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Customers per aplikasi: Event (/events/customers), Pages (/pages/customers), Invoice (/invoices/customers),
 * dan superadmin (Data User › Customer, /superadmin/customers: semua aplikasi, export & import). Customers WhatsApp ada di Phone Book.
 */
class CustomerController extends Controller
{
    private const ROUTE_APPS = ['events.customers' => 'events', 'pages.customers' => 'pages', 'invoices.customers' => 'invoices'];

    /** Aplikasi yang customernya punya riwayat isian page (pembeli tiket, pesanan katalog, peserta kuis). */
    private const SUBMISSION_APPS = ['events', 'pages'];

    /** Batas baris sekali export supaya server tetap ringan; lebih dari itu persempit dengan filter. */
    public const EXPORT_LIMIT = 20000;

    public function index(Request $request): View
    {
        $user = $request->user();
        [$base, $app] = $this->context($request);
        $filterApp = $app ?? (array_key_exists((string) $request->query('app'), Customer::APPS) ? $request->query('app') : null);

        $customers = $this->filtered($request, $filterApp)
            ->with(['branch:id,name', 'company:id,name'])
            ->when(in_array($app, self::SUBMISSION_APPS, true), fn ($q) => $q->withCount('submissions'))
            ->when($app === 'invoices', fn ($q) => $q->withCount('invoices'))
            ->latest('last_seen_at')
            ->paginate(20)
            ->withQueryString();

        return view('contact.customer.index', [
            'customers' => $customers,
            'app' => $app,
            // Superadmin memfilter lewat pencarian; owner memilih dari branch miliknya.
            'branches' => $user->isPlatformTeam() ? collect() : UserBranch::where('user_id', $user->getKey())->orderBy('name')->get(['id', 'name']),
            // Branch yang boleh ditambah customer: paket aplikasinya aktif. Superadmin: semua branch aktif, dicek lagi per aplikasi saat import.
            'writable' => $app ? $this->writableBranches($request, $app)
                : ($user->canAccess('superadmin.customers', \App\Support\Menu::ABILITY_MANAGE) ? $this->activeBranches(Apps::ANY) : collect()),
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
            // Link ke pesanan tiket ada di menu Event, pesanan lain di menu Pages.
            'pagesRoute' => ($admin ? 'superadmin.' : '').($model->app_key === 'events' ? 'events' : 'pages'),
            'invoiceRoute' => $admin ? null : 'invoices.show',
            'submissions' => in_array($model->app_key, self::SUBMISSION_APPS, true) ? $model->submissions()->with(['page:id,name', 'ticketOrder:id,submission_id'])->latest()->paginate(15) : null,
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

    public function update(Request $request, string $customer, CustomerAccount $accounts): RedirectResponse
    {
        $model = $this->owned($request, $customer);
        $data = CustomerService::validate($request, $model->branch_id, $model->app_key, $model);
        $model->forceFill(['name' => $data['name'], 'phone' => $data['phone'], 'email' => $data['email']])->save();

        // Dari tombol "Jadikan User" pada customer yang belum punya email.
        return $request->boolean('make_user') ? $this->grantAccount($model, $accounts) : back()->with('success', 'Data customer diperbarui.');
    }

    public function destroy(Request $request, string $customer): RedirectResponse
    {
        $model = $this->owned($request, $customer);
        $model->delete();

        return back()->with('success', "{$model->displayName()} dihapus dari Customers. Riwayat transaksi/invoice tetap tersimpan.");
    }

    /** Hapus banyak (superadmin): yang dicentang, atau semua yang cocok dengan filter (all=1, filter dibawa di query string). */
    public function destroyMany(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'all' => ['nullable', 'boolean'],
            'ids' => ['required_unless:all,1', 'array', 'max:1000'],
            'ids.*' => ['uuid'],
        ], ['ids.required_unless' => 'Pilih customer yang mau dihapus dulu.']);

        $filterApp = array_key_exists((string) $request->query('app'), Customer::APPS) ? $request->query('app') : null;
        $query = $request->boolean('all')
            ? $this->filtered($request, $filterApp)
            : Customer::accessibleBy($request->user())->whereKey($data['ids']);

        $deleted = 0;
        $query->select('id')->chunkById(500, function ($rows) use (&$deleted) {
            $deleted += Customer::whereKey($rows->modelKeys())->delete();
        });

        return back()->with('success', number_format($deleted, 0, ',', '.').' customer dihapus. Riwayat transaksi/invoice tetap tersimpan.');
    }

    /** Export Excel sesuai filter yang sedang dipakai (superadmin). */
    public function export(Request $request): StreamedResponse|RedirectResponse
    {
        $filterApp = array_key_exists((string) $request->query('app'), Customer::APPS) ? $request->query('app') : null;
        $query = $this->filtered($request, $filterApp);

        if (($total = (clone $query)->count()) > self::EXPORT_LIMIT) {
            return back()->with('error', 'Ada '.number_format($total, 0, ',', '.').' customer. Maksimal '.number_format(self::EXPORT_LIMIT, 0, ',', '.').' per export, persempit dulu dengan filter aplikasi atau pencarian.');
        }

        $rows = $query->with(['branch:id,name', 'company:id,name'])->lazyById(500)->chunk(500)
            ->flatMap(function ($chunk) {
                $accounts = CustomerAccount::emailsWithAccount($chunk);

                return $chunk->map(fn (Customer $c) => [
                    $c->name, $c->phone ? '+'.$c->phone : '', $c->email, $c->appLabel(), $c->company?->name, $c->branch?->name,
                    $c->source, $c->email && isset($accounts[strtolower($c->email)]) ? 'Ya' : 'Tidak',
                    $c->first_seen_at?->format('Y-m-d H:i'), $c->last_seen_at?->format('Y-m-d H:i'),
                ]);
            });

        return XlsxExport::download('customers-'.now()->format('Ymd-His'),
            ['Nama', 'WhatsApp', 'Email', 'Aplikasi', 'Perusahaan', 'Branch', 'Sumber', 'Punya Akun', 'Pertama Masuk', 'Terakhir'], $rows);
    }

    public function import(Request $request, ContactBookService $book): RedirectResponse
    {
        [, $app] = $this->context($request);
        // Superadmin memilih aplikasi tujuan; owner sudah ditentukan oleh menunya.
        $app ??= $request->validate(['app' => ['required', Rule::in(array_keys(Customer::APPS))]], ['app.*' => 'Pilih aplikasi tujuan.'])['app'];
        $branch = $this->writableBranch($request, $app);
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120']], ['file.mimes' => 'File harus Excel (.xlsx/.xls) atau CSV.', 'file.max' => 'Ukuran file maksimal 5 MB.']);

        [$rows, $valid] = $book->read($request->file('file'));
        if (! $valid) {
            return back()->with('error', 'Kolom WhatsApp/email tidak ditemukan. Baris pertama harus berisi judul kolom: nama, whatsapp, email. Pakai file contoh supaya pas.');
        }

        $result = $book->addMany($branch, $rows, null, 'import', $app);

        if ($request->user()->isPlatformTeam()) {
            return redirect()->route('superadmin.contacts.index', ['app' => $app, 'search' => null])->with('success', "{$result['saved']} customer diimpor ke {$branch->name} (".Customer::APPS[$app].')'.($result['skipped'] ? ", {$result['skipped']} baris dilewati karena tanpa WhatsApp dan email." : '.'));
        }

        return back()->with('success', "{$result['saved']} customer diimpor".($result['skipped'] ? ", {$result['skipped']} baris dilewati karena tanpa WhatsApp dan email." : '.'));
    }

    public function template(Request $request, ContactBookService $book): StreamedResponse
    {
        [, $app] = $this->context($request);

        return $book->template('contoh-customers-'.($app ?? 'bizbos').'.xlsx');
    }

    public function account(Request $request, string $customer, CustomerAccount $accounts): RedirectResponse
    {
        return $this->grantAccount($this->owned($request, $customer), $accounts);
    }

    private function grantAccount(Customer $customer, CustomerAccount $accounts): RedirectResponse
    {
        try {
            [$user, $created] = $accounts->grant($customer);
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

    /** Filter cari, aplikasi, dan branch yang sama untuk tabel dan export. */
    private function filtered(Request $request, ?string $app): Builder
    {
        return Customer::accessibleBy($request->user())
            ->when($app, fn ($q, $a) => $q->forApp($a))
            ->search($request->query('search'))
            ->when($request->query('branch'), fn ($q, $id) => $q->where('branch_id', $id));
    }

    private function writableBranches(Request $request, string $app)
    {
        // Superadmin boleh import ke branch mana pun yang paket aplikasinya aktif; owner hanya branch miliknya.
        return $request->user()->isPlatformTeam()
            ? $this->activeBranches($app)
            : UserBranch::whereIn('id', app(AppAccess::class)->ownerBranchIds($request->user(), $app))->orderBy('name')->get(['id', 'name', 'company_id']);
    }

    private function activeBranches(string $app)
    {
        return UserBranch::with('company:id,name')->where('status', 'active')->whereIn('id', AppAccess::activeBranches($app))
            ->orderBy('name')->get(['id', 'name', 'company_id']);
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

<?php

namespace App\Http\Controllers\Contact;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\UserBranch;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Customers per aplikasi: Pages (/pages/customers), Invoice (/invoices/customers),
 * dan superadmin (/superadmin/contacts, semua aplikasi). Customers WhatsApp ada di Phone Book.
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

    /** @return array{0: string, 1: ?string} [nama route dasar, app_key; null = semua (superadmin)] */
    private function context(Request $request): array
    {
        $base = (string) str($request->route()?->getName())->beforeLast('.');

        return [$base, self::ROUTE_APPS[$base] ?? null];
    }
}

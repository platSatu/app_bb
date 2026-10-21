<?php

namespace App\Http\Controllers\Contact;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\UserBranch;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Buku Kontak, dipakai bersama user (/contacts) & superadmin (/superadmin/contacts). */
class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $customers = Customer::accessibleBy($user)
            ->with(['branch:id,name', 'company:id,name'])
            ->withCount('submissions')
            ->search($request->query('search'))
            ->when($request->query('branch'), fn ($q, $id) => $q->where('branch_id', $id))
            ->latest('last_seen_at')
            ->paginate(20)
            ->withQueryString();

        return view('contact.customer.index', [
            'customers' => $customers,
            // Superadmin memfilter lewat pencarian; owner memilih dari branch miliknya.
            'branches' => $user->isPlatformTeam() ? collect() : UserBranch::where('user_id', $user->getKey())->orderBy('name')->get(['id', 'name']),
            'routes' => $this->routes($request),
        ]);
    }

    public function show(Request $request, string $customer): View
    {
        $model = Customer::accessibleBy($request->user())->with(['branch:id,name', 'company:id,name'])->findOrFail($customer);

        return view('contact.customer.show', [
            'customer' => $model,
            'submissions' => $model->submissions()->with(['page:id,name', 'ticketOrder:id,submission_id'])->latest()->paginate(15),
            'routes' => $this->routes($request),
        ]);
    }

    private function routes(Request $request): array
    {
        $admin = str_starts_with((string) $request->route()?->getName(), 'superadmin.');

        return ['contacts' => $admin ? 'superadmin.contacts' : 'contacts', 'pages' => $admin ? 'superadmin.pages' : 'pages'];
    }
}

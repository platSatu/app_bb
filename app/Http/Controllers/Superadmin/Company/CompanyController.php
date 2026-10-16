<?php

namespace App\Http\Controllers\Superadmin\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\Company\CompanyRequest;
use App\Models\User;
use App\Models\UserCompany;
use App\Services\Company\CompanyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function __construct(private readonly CompanyService $companies)
    {
    }

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $companies = UserCompany::query()
            ->with('user:id,name,email')
            ->withCount('branches')
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('superadmin.company.index', compact('companies'));
    }

    public function create(): View
    {
        return view('superadmin.company.create', [
            'company' => new UserCompany(['status' => UserCompany::STATUS_ACTIVE]),
            'owners' => User::query()->whereDoesntHave('company')->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(CompanyRequest $request): RedirectResponse
    {
        $company = $this->companies->saveByAdmin(new UserCompany(), $request->safe()->except('logo'), $request->file('logo'));

        return redirect()->route('superadmin.companies.index')
            ->with('success', "Perusahaan {$company->name} berhasil ditambahkan.");
    }

    public function edit(UserCompany $company): View
    {
        return view('superadmin.company.edit', ['company' => $company->load('user:id,name,email')]);
    }

    public function update(CompanyRequest $request, UserCompany $company): RedirectResponse
    {
        $this->companies->saveByAdmin($company, $request->safe()->except('logo'), $request->file('logo'));

        return redirect()->route('superadmin.companies.index')
            ->with('success', "Data {$company->name} berhasil diperbarui.");
    }

    public function destroy(UserCompany $company): RedirectResponse
    {
        $this->companies->delete($company);

        return redirect()->route('superadmin.companies.index')
            ->with('success', "Perusahaan {$company->name} beserta branch-nya sudah dihapus.");
    }
}

<?php

namespace App\Http\Controllers\Superadmin\Branch;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\Branch\BranchRequest;
use App\Models\UserBranch;
use App\Models\UserCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function index(Request $request): View
    {
        $companyId = $request->query('company');

        $branches = UserBranch::query()
            ->with('company:id,name')
            ->search($request->query('search'))
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('superadmin.branch.index', [
            'branches' => $branches,
            'companies' => $this->companyOptions(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('superadmin.branch.create', [
            'branch' => new UserBranch(['status' => UserBranch::STATUS_ACTIVE]),
            'companies' => $this->companyOptions(),
            'selectedCompany' => $request->query('company'),
        ]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        $company = UserCompany::findOrFail($request->validated('company_id'));

        $branch = new UserBranch($request->safe()->except('company_id'));
        $branch->company_id = $company->getKey();
        $branch->user_id = $company->user_id;
        $branch->save();

        return redirect()->route('superadmin.branches.index')
            ->with('success', "Branch {$branch->name} berhasil ditambahkan ke {$company->name}.");
    }

    public function edit(UserBranch $branch): View
    {
        return view('superadmin.branch.edit', ['branch' => $branch->load('company:id,name')]);
    }

    public function update(BranchRequest $request, UserBranch $branch): RedirectResponse
    {
        $branch->update($request->validated());

        return redirect()->route('superadmin.branches.index')
            ->with('success', "Branch {$branch->name} berhasil diperbarui.");
    }

    public function destroy(UserBranch $branch): RedirectResponse
    {
        $branch->delete();

        return redirect()->route('superadmin.branches.index')
            ->with('success', "Branch {$branch->name} sudah dihapus.");
    }

    private function companyOptions()
    {
        return UserCompany::query()->orderBy('name')->get(['id', 'name']);
    }
}

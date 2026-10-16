<?php

namespace App\Http\Controllers\Settings\Branch;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\Branch\BranchRequest;
use App\Models\UserBranch;
use App\Models\UserCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Branch selalu milik perusahaan user yang login. company_id & user_id
 * diambil dari sesi login, tidak pernah dari form atau URL.
 */
class BranchController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        if (! $company = $request->user()->company) {
            return $this->companyRequired();
        }

        $branches = $company->branches()
            ->search($request->query('search'))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('settings.branch.index', compact('branches'));
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->user()->company) {
            return $this->companyRequired();
        }

        return view('settings.branch.create', ['branch' => new UserBranch(['status' => UserBranch::STATUS_ACTIVE])]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        $branch = new UserBranch($request->validated());
        $branch->user_id = $request->user()->getKey();
        $branch->company_id = $request->user()->company->getKey();
        $branch->save();

        return redirect()->route('settings.branches.index')
            ->with('success', "Branch {$branch->name} berhasil ditambahkan.");
    }

    public function edit(Request $request, string $branch): View
    {
        return view('settings.branch.edit', ['branch' => $this->ownedBranch($request, $branch)]);
    }

    public function update(BranchRequest $request, string $branch): RedirectResponse
    {
        $model = $this->ownedBranch($request, $branch);
        $model->update($request->validated());

        return redirect()->route('settings.branches.index')
            ->with('success', "Branch {$model->name} berhasil diperbarui.");
    }

    public function destroy(Request $request, string $branch): RedirectResponse
    {
        $model = $this->ownedBranch($request, $branch);
        $model->delete();

        return redirect()->route('settings.branches.index')
            ->with('success', "Branch {$model->name} sudah dihapus.");
    }

    private function ownedBranch(Request $request, string $id): UserBranch
    {
        $company = $request->user()->company;
        abort_unless($company instanceof UserCompany, 404);

        return $company->branches()->findOrFail($id);
    }

    private function companyRequired(): RedirectResponse
    {
        return redirect()->route('settings.company.edit')
            ->with('info', 'Lengkapi profil perusahaan dulu ya, setelah itu Anda bisa menambah branch.');
    }
}

<?php

namespace App\Http\Controllers\Settings\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\Company\CompanyRequest;
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

    /** Sudah diisi = tampil sebagai data; belum = langsung ke form. */
    public function show(Request $request): View|RedirectResponse
    {
        $company = $request->user()->company()->withCount('branches')->first();

        return $company
            ? view('settings.company.show', compact('company'))
            : redirect()->route('settings.company.edit');
    }

    public function edit(Request $request): View
    {
        return view('settings.company.edit', [
            'company' => $request->user()->company ?? new UserCompany(),
        ]);
    }

    public function update(CompanyRequest $request): RedirectResponse
    {
        $this->companies->saveProfile(
            $request->user(),
            $request->safe()->except('logo'),
            $request->file('logo'),
        );

        return redirect()->route('settings.company.show')
            ->with('success', 'Profil perusahaan berhasil disimpan.');
    }
}

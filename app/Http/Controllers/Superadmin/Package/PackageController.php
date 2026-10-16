<?php

namespace App\Http\Controllers\Superadmin\Package;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\Package\PackageRequest;
use App\Models\CategoryApplication;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PackageController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search'));

        $packages = Package::query()
            ->with('category:id,name')
            ->withCount('vouchers')
            ->when($request->query('category'), fn ($q, $id) => $q->where('category_application_id', $id))
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('category_application_id')
            ->orderBy('price')
            ->paginate(15)
            ->withQueryString();

        return view('superadmin.package.index', ['packages' => $packages, 'categories' => $this->categories()]);
    }

    public function create(): View
    {
        return view('superadmin.package.create', [
            'package' => new Package(['status' => Package::STATUS_ACTIVE, 'duration' => 30]),
            'categories' => $this->categories(),
        ]);
    }

    public function store(PackageRequest $request): RedirectResponse
    {
        $package = Package::create($request->validated());

        return redirect()->route('superadmin.packages.index')->with('success', "Package {$package->name} berhasil ditambahkan.");
    }

    public function edit(Package $package): View
    {
        return view('superadmin.package.edit', ['package' => $package, 'categories' => $this->categories()]);
    }

    public function update(PackageRequest $request, Package $package): RedirectResponse
    {
        $package->update($request->validated());

        return redirect()->route('superadmin.packages.index')->with('success', "Package {$package->name} berhasil diperbarui.");
    }

    public function destroy(Package $package): RedirectResponse
    {
        if ($package->vouchers()->exists()) {
            return back()->with('error', "Package {$package->name} sudah pernah dibeli, jadi tidak bisa dihapus. Nonaktifkan saja supaya tidak dijual lagi.");
        }

        $package->delete();

        return redirect()->route('superadmin.packages.index')->with('success', "Package {$package->name} sudah dihapus.");
    }

    private function categories()
    {
        return CategoryApplication::orderBy('name')->get(['id', 'name', 'status']);
    }
}

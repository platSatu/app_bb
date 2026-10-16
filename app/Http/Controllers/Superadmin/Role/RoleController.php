<?php

namespace App\Http\Controllers\Superadmin\Role;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\Role\RoleRequest;
use App\Models\Role;
use App\Services\Role\RoleService;
use App\Support\Menu;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles)
    {
    }

    public function index(): View
    {
        $roles = Role::platform()
            ->withCount(['users', 'permissions'])
            ->orderBy('name')
            ->paginate(15);

        return view('superadmin.role.index', compact('roles'));
    }

    public function create(): View
    {
        return view('superadmin.role.create', $this->formData(new Role()));
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = $this->roles->savePlatformRole(new Role(), $request->safe()->only(['name', 'description']), $request->permissions());

        return redirect()->route('superadmin.roles.index')
            ->with('success', "Role {$role->name} berhasil dibuat.");
    }

    public function edit(Role $role): View
    {
        return view('superadmin.role.edit', $this->formData($role));
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        $this->roles->savePlatformRole($role, $request->safe()->only(['name', 'description']), $request->permissions());

        return redirect()->route('superadmin.roles.index')
            ->with('success', "Role {$role->name} berhasil diperbarui. Perubahan izin langsung berlaku.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        $staffCount = $role->users()->count();
        $role->delete();

        return redirect()->route('superadmin.roles.index')
            ->with('success', $staffCount > 0
                ? "Role {$role->name} dihapus. {$staffCount} staff yang memakai role ini sekarang tidak punya akses menu."
                : "Role {$role->name} dihapus.");
    }

    private function formData(Role $role): array
    {
        return [
            'role' => $role,
            'menus' => Menu::grantable('superadmin'),
            'granted' => $role->exists ? $role->permissions()->pluck('ability', 'permission_key')->all() : [],
        ];
    }
}

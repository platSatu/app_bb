<?php

namespace App\Http\Controllers\Superadmin\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\User\UserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users)
    {
    }

    public function index(Request $request): View
    {
        $type = $request->query('type');

        $users = User::query()
            ->with('roles:id,name')
            ->search($request->query('search'))
            ->when(array_key_exists((string) $type, User::TYPE_LABELS), fn ($q) => $q->where('user_type', $type))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $stats = User::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(status = ?) as active', [User::STATUS_ACTIVE])
            ->selectRaw('sum(status = ?) as inactive', [User::STATUS_INACTIVE])
            ->selectRaw('sum(user_type = ?) as staff', [User::TYPE_STAFF])
            ->first();

        return view('superadmin.user.index', compact('users', 'stats'));
    }

    public function create(): View
    {
        return view('superadmin.user.create', ['user' => new User(), 'roles' => $this->platformRoles()]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = $this->users->save(new User(), $request->validated(), $request->user());

        return redirect()->route('superadmin.users.index')
            ->with('success', "User {$user->name} berhasil ditambahkan dan langsung aktif.");
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('superadmin.user.edit', ['user' => $user->load('roles:id'), 'roles' => $this->platformRoles()]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->users->save($user, $request->validated(), $request->user());

        return redirect()->route('superadmin.users.index')
            ->with('success', "Data {$user->name} berhasil diperbarui.");
    }

    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        if ($user->company()->exists()) {
            return back()->with('error', "User {$user->name} masih punya data perusahaan, jadi belum bisa dihapus. Nonaktifkan saja akunnya.");
        }

        $user->delete();

        return redirect()->route('superadmin.users.index')
            ->with('success', "User {$user->name} sudah dihapus.");
    }

    private function platformRoles()
    {
        return Role::platform()->orderBy('name')->get(['id', 'name']);
    }
}

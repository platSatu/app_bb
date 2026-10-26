<?php

namespace App\Http\Controllers\Superadmin\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Superadmin\User\UserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\User\UserPurger;
use App\Services\User\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

    /**
     * Reset total: user beserta SEMUA datanya. Wajib ketik ulang email user + password login admin yang menghapus,
     * dan ditolak kalau masih ada uang yang berjalan.
     */
    public function destroy(Request $request, User $user, UserPurger $purger): RedirectResponse
    {
        Gate::authorize('delete', $user);

        // Salah password berulang dikunci sebentar supaya tidak bisa ditebak.
        $limiter = 'user-purge:'.$request->user()->getKey();
        if (RateLimiter::tooManyAttempts($limiter, 5)) {
            return back()->with('error', 'Terlalu banyak percobaan. Coba lagi dalam '.RateLimiter::availableIn($limiter).' detik.');
        }
        RateLimiter::hit($limiter, 60);

        $request->validate(['password' => ['required', 'current_password']], ['password.current_password' => 'Password login Anda salah. User tidak dihapus.']);

        if (strtolower(trim((string) $request->input('confirm_email'))) !== strtolower($user->email)) {
            return back()->with('error', "Email konfirmasi tidak cocok. User {$user->name} tidak dihapus.");
        }

        if ($blockers = $purger->blockers($user)) {
            return back()->with('error', "User {$user->name} belum bisa dihapus karena ".implode(', ', $blockers).'. Selesaikan dulu, lalu coba lagi.');
        }

        $rows = array_sum($purger->purge($user));

        return redirect()->route('superadmin.users.index')
            ->with('success', "User {$user->name} beserta seluruh datanya sudah dihapus permanen (".number_format($rows, 0, ',', '.').' baris data).');
    }

    private function platformRoles()
    {
        return Role::platform()->orderBy('name')->get(['id', 'name']);
    }
}

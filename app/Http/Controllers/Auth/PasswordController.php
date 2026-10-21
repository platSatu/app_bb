<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $user = $request->user();
        $user->update(['password' => $validated['password']]);

        // Perangkat lain yang masih login dengan password lama ikut dikeluarkan.
        $user->logoutEverywhere(exceptSessionId: $request->session()->getId());

        return redirect()->route($user->isMember() ? 'portal.profile' : 'profile.edit')
            ->with('success', 'Password berhasil diganti. Perangkat lain yang masih login sudah kami keluarkan.');
    }
}

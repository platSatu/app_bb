<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\Turnstile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'cf-turnstile-response' => [new Turnstile()],
        ]);

        // Pesan sama untuk email terdaftar maupun tidak, supaya daftar email tidak bisa ditebak.
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'Kalau email tersebut terdaftar, link reset password sudah kami kirim. Silakan cek inbox atau folder spam.');
    }
}

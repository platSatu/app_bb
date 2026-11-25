<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Kuota paket habis. Tampil sendiri: API/JSON = 429 {success, message}, halaman = kembali dengan pesan error. */
class PlanLimitException extends RuntimeException
{
    /**
     * Tombol di pesan error halaman (mis. "Tambah kuota murid" ke daftar paket). Disimpan ke flash saat dilempar,
     * karena sebagian controller menangkap RuntimeException lalu hanya meneruskan pesannya. Hanya nama route
     * internal yang diterima, jadi tidak bisa diarahkan ke alamat luar.
     */
    public function offer(string $label, string $route): static
    {
        $request = app()->bound('request') ? request() : null;

        if ($request && $request->hasSession() && ! $request->expectsJson() && \Illuminate\Support\Facades\Route::has($route)) {
            $request->session()->flash('error_action', ['label' => $label, 'url' => route($route)]);
        }

        return $this;
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        return $request->is('api/*') || $request->expectsJson()
            ? response()->json(['success' => false, 'code' => 'plan_limit', 'message' => $this->getMessage()], 429)
            : back()->withInput()->with('error', $this->getMessage());
    }
}

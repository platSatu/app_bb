<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Kuota paket habis. Tampil sendiri: API/JSON = 429 {success, message}, halaman = kembali dengan pesan error. */
class PlanLimitException extends RuntimeException
{
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        return $request->is('api/*') || $request->expectsJson()
            ? response()->json(['success' => false, 'code' => 'plan_limit', 'message' => $this->getMessage()], 429)
            : back()->withInput()->with('error', $this->getMessage());
    }
}

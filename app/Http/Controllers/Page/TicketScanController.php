<?php

namespace App\Http\Controllers\Page;

use App\Http\Controllers\Controller;
use App\Models\TicketScanner;
use App\Services\Ticket\TicketCheckinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/** Halaman scan untuk petugas: link ber-token + PIN, sesi berlaku di browser petugas saja. */
class TicketScanController extends Controller
{
    private const MAX_PIN_ATTEMPTS = 5;

    public function show(Request $request, TicketCheckinService $checkins, string $token): View
    {
        $scanner = $this->scanner($token);

        if (! $this->authorized($request, $scanner)) {
            return view('page.public.scan-login', ['scanner' => $scanner]);
        }

        return view('page.public.scan', ['scanner' => $scanner, 'page' => $scanner->page, 'stats' => $checkins->stats($scanner->page)]);
    }

    public function login(Request $request, string $token): RedirectResponse
    {
        $scanner = $this->scanner($token);
        $key = 'scan-pin:'.$scanner->getKey().':'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_PIN_ATTEMPTS)) {
            return back()->withErrors(['pin' => 'Terlalu banyak percobaan. Coba lagi dalam '.ceil(RateLimiter::availableIn($key) / 60).' menit.']);
        }

        $pin = (string) $request->validate(['pin' => ['required', 'digits:6']], ['pin.digits' => 'PIN terdiri dari 6 angka.'])['pin'];

        if (! Hash::check($pin, $scanner->pin_hash)) {
            RateLimiter::hit($key, 600);

            return back()->withErrors(['pin' => 'PIN salah. Tanyakan PIN yang benar ke penyelenggara.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        // Hash PIN ikut disimpan: kalau owner mengganti PIN, sesi lama otomatis tidak berlaku.
        $request->session()->put($this->sessionKey($scanner), sha1($scanner->pin_hash));

        return redirect()->route('scan.show', $token);
    }

    public function check(Request $request, TicketCheckinService $checkins, string $token): JsonResponse
    {
        $scanner = $this->scanner($token);

        if (! $this->authorized($request, $scanner)) {
            return response()->json(['status' => 'logged_out', 'message' => 'Sesi berakhir, silakan masukkan PIN lagi.'], 401);
        }

        $data = $request->validate(['code' => ['required', 'string', 'max:64'], 'confirm' => ['boolean']]);
        $result = $checkins->scan($scanner->page, $scanner, $data['code'], $request->boolean('confirm'));

        return response()->json([...$result, 'stats' => $checkins->stats($scanner->page)]);
    }

    public function logout(Request $request, string $token): RedirectResponse
    {
        $scanner = $this->scanner($token);
        $request->session()->forget($this->sessionKey($scanner));

        return redirect()->route('scan.show', $token);
    }

    private function scanner(string $token): TicketScanner
    {
        $scanner = TicketScanner::with('page')->where('token', $token)->firstOrFail();
        abort_unless($scanner->isActive(), 403, 'Link petugas ini sudah dinonaktifkan oleh penyelenggara.');

        return $scanner;
    }

    private function authorized(Request $request, TicketScanner $scanner): bool
    {
        $stored = $request->session()->get($this->sessionKey($scanner));

        return is_string($stored) && hash_equals(sha1($scanner->pin_hash), $stored);
    }

    private function sessionKey(TicketScanner $scanner): string
    {
        return 'ticket_scanner.'.$scanner->getKey();
    }
}

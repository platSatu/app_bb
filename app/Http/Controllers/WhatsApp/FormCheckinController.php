<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Models\WaFormIntegration;
use App\Models\WaFormSubmission;
use App\Services\Media\QrCodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Halaman publik Google Form: QR pendaftar (/r/{kode}) dan aplikasi registrasi ulang petugas (/registrasi-ulang/{token}, ber-PIN).
 * Pola sama dengan scanner tiket: PIN di-hash, sesi ikut putus kalau PIN diganti, salah PIN dibatasi.
 */
class FormCheckinController extends Controller
{
    private const MAX_PIN_ATTEMPTS = 5;

    public function ticket(string $code, QrCodeGenerator $qr): View
    {
        $submission = WaFormSubmission::with('integration.branch.company:id,name', 'invoice:id,status,token')->where('code', strtoupper($code))->firstOrFail();

        return view('whatsapp.google-form.public.ticket', [
            'submission' => $submission,
            'integration' => $submission->integration,
            'qr' => $submission->isPaid() ? $qr->dataUri($submission->code) : null,
        ]);
    }

    public function show(Request $request, string $token): View
    {
        $integration = $this->integration($token);

        if (! $this->authorized($request, $integration)) {
            return view('whatsapp.google-form.public.checkin-login', ['integration' => $integration]);
        }

        return view('whatsapp.google-form.public.checkin', [
            'integration' => $integration,
            'stats' => $this->stats($integration),
        ]);
    }

    public function login(Request $request, string $token): RedirectResponse
    {
        $integration = $this->integration($token);
        $key = 'form-checkin-pin:'.$integration->id.':'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_PIN_ATTEMPTS)) {
            return back()->withErrors(['pin' => 'Terlalu banyak percobaan. Coba lagi dalam '.ceil(RateLimiter::availableIn($key) / 60).' menit.']);
        }

        $pin = (string) $request->validate(['pin' => ['required', 'digits:6']], ['pin.digits' => 'PIN terdiri dari 6 angka.'])['pin'];
        if (! Hash::check($pin, (string) $integration->checkin_pin_hash)) {
            RateLimiter::hit($key, 600);

            return back()->withErrors(['pin' => 'PIN salah. Tanyakan PIN yang benar ke penyelenggara.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $request->session()->put($this->sessionKey($integration), sha1($integration->checkin_pin_hash));

        return redirect()->route('form-checkin.show', $token);
    }

    public function logout(Request $request, string $token): RedirectResponse
    {
        $request->session()->forget($this->sessionKey($this->integration($token)));

        return redirect()->route('form-checkin.show', $token);
    }

    /** Petugas scan QR / ketik kode. Satu kode hanya bisa hadir sekali (dikunci di database). */
    public function check(Request $request, string $token): JsonResponse
    {
        $integration = $this->integration($token);
        abort_unless($this->authorized($request, $integration), 401);

        $code = strtoupper(trim((string) $request->validate(['code' => ['required', 'string', 'max:255']])['code']));
        // QR berisi kode; kalau yang terbaca URL /r/{kode}, ambil bagian akhirnya.
        $code = substr((string) preg_replace('~.*/~', '', $code), 0, 12);
        $row = $integration->submissions()->with('invoice:id,status')->where('code', $code)->first();

        $result = match (true) {
            ! $row => ['state' => 'invalid', 'title' => 'Kode tidak dikenal', 'text' => 'Pastikan QR/kode dari pendaftaran '.$integration->name.'.'],
            ! $row->isPaid() => ['state' => 'unpaid', 'title' => 'Belum lunas', 'text' => $row->displayName().' belum menyelesaikan pembayaran.'],
            ! WaFormSubmission::whereKey($row->id)->whereNull('attended_at')->update(['attended_at' => now()]) => [
                'state' => 'duplicate', 'title' => 'Sudah registrasi ulang', 'text' => $row->displayName().' sudah hadir pukul '.$row->fresh()->attended_at?->timezone(config('app.timezone'))->format('H:i').'.',
            ],
            default => ['state' => 'ok', 'title' => 'Selamat datang!', 'text' => $row->displayName().' berhasil registrasi ulang.'],
        };

        return response()->json([...$result, 'name' => $row?->displayName(), 'stats' => $this->stats($integration)]);
    }

    private function integration(string $token): WaFormIntegration
    {
        return WaFormIntegration::with('branch.company:id,name')->where('checkin_token', $token)->where('registration_enabled', true)->firstOrFail();
    }

    private function authorized(Request $request, WaFormIntegration $integration): bool
    {
        return $integration->checkin_pin_hash && hash_equals(sha1($integration->checkin_pin_hash), (string) $request->session()->get($this->sessionKey($integration)));
    }

    private function sessionKey(WaFormIntegration $integration): string
    {
        return 'form-checkin:'.$integration->id;
    }

    private function stats(WaFormIntegration $integration): array
    {
        return [
            'hadir' => $integration->submissions()->whereNotNull('attended_at')->count(),
            'total' => $integration->submissions()->whereNotNull('code')->count(),
        ];
    }
}

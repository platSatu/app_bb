<?php

namespace App\Http\Controllers\Page;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketCardLog;
use App\Models\TicketScanner;
use App\Models\TicketSession;
use App\Services\Ticket\TicketAttendanceService;
use App\Services\Ticket\TicketCardService;
use App\Services\Ticket\TicketCheckinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Halaman perangkat di lokasi event: link ber-token + PIN, sesi berlaku di browser itu saja.
 * Jenis link menentukan halamannya: registrasi ulang, kiosk cetak ID card, atau absensi kelas.
 */
class TicketScanController extends Controller
{
    private const MAX_PIN_ATTEMPTS = 5;

    public function show(Request $request, TicketCheckinService $checkins, TicketAttendanceService $attendance, string $token): View
    {
        $scanner = $this->scanner($token);

        if (! $this->authorized($request, $scanner)) {
            return view('page.public.scan-login', ['scanner' => $scanner]);
        }

        $page = $scanner->page;

        return match (true) {
            $scanner->isType(TicketScanner::TYPE_KIOSK) => view('page.public.kiosk', ['scanner' => $scanner, 'page' => $page]),
            $scanner->isType(TicketScanner::TYPE_ATTENDANCE) => view('page.public.scan-attendance', [
                'scanner' => $scanner,
                'page' => $page,
                'sessions' => $sessions = TicketSession::where('page_id', $page->getKey())->active()->orderBy('starts_at')->get(),
                'stats' => $sessions->mapWithKeys(fn ($s) => [$s->getKey() => $attendance->stats($s)]),
            ]),
            default => view('page.public.scan', ['scanner' => $scanner, 'page' => $page, 'stats' => $checkins->stats($page), 'cardEnabled' => TicketCardService::enabled($page)]),
        };
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

    /** Registrasi ulang (link petugas) atau absensi kelas (link absensi). */
    public function check(Request $request, TicketCheckinService $checkins, TicketAttendanceService $attendance, string $token): JsonResponse
    {
        $scanner = $this->scanner($token);

        if (! $this->authorized($request, $scanner)) {
            return $this->loggedOut();
        }

        $data = $request->validate(['code' => ['required', 'string', 'max:64'], 'confirm' => ['boolean'], 'session_id' => ['nullable', 'uuid']]);

        if ($scanner->isType(TicketScanner::TYPE_ATTENDANCE)) {
            $result = $attendance->scan($scanner->page, $scanner, $data['session_id'] ?? null, $data['code'], $request->boolean('confirm'));
            $session = isset($data['session_id']) ? TicketSession::where('page_id', $scanner->page_id)->find($data['session_id']) : null;

            return response()->json([...$result, 'stats' => $session ? $attendance->stats($session) : null]);
        }

        abort_unless($scanner->isType(TicketScanner::TYPE_CHECKIN), 404);
        $result = $checkins->scan($scanner->page, $scanner, $data['code'], $request->boolean('confirm'));

        return response()->json([...$result, 'stats' => $checkins->stats($scanner->page)]);
    }

    /**
     * Kiosk: confirm=false -> cek pemilik e-ticket (belum mengubah apa pun),
     * confirm=true -> ID card dibuat & dikirim sebagai dokumen siap cetak.
     */
    public function redeem(Request $request, TicketCardService $cards, string $token): JsonResponse
    {
        $scanner = $this->scanner($token);

        if (! $this->authorized($request, $scanner)) {
            return $this->loggedOut();
        }

        abort_unless($scanner->isType(TicketScanner::TYPE_KIOSK), 404);
        $data = $request->validate(['code' => ['required', 'string', 'max:64'], 'confirm' => ['boolean']]);
        $page = $scanner->page;

        $result = $request->boolean('confirm')
            ? $cards->redeem($page, $scanner, $data['code'])
            : $cards->lookup($page, $data['code']);

        $ticket = $result['ticket'] ?? null;
        unset($result['ticket']);

        if ($ticket) {
            $result['ticket'] = ['name' => $ticket->holder_name, 'category' => $ticket->category_name];
        }

        if ($result['status'] === 'ready') {
            $result['preview'] = view('page.public._id-card', ['card' => $cards->cardData($page, TicketCardService::settings($page), $ticket)])->render();
        }

        if ($result['status'] === 'ok') {
            $result['print'] = $cards->render($page, $ticket);
        }

        return response()->json($result);
    }

    /** Petugas registrasi ulang: cetak / cetak ulang ID card untuk peserta yang ada di depannya. */
    public function card(Request $request, TicketCardService $cards, string $token): JsonResponse
    {
        $scanner = $this->scanner($token);

        if (! $this->authorized($request, $scanner)) {
            return $this->loggedOut();
        }

        abort_unless($scanner->isType(TicketScanner::TYPE_CHECKIN), 404);
        $page = $scanner->page;

        if (! TicketCardService::enabled($page)) {
            return response()->json(['status' => 'disabled', 'message' => 'Cetak ID card belum diaktifkan penyelenggara.']);
        }

        $data = $request->validate(['number' => ['required', 'string', 'regex:/^BB\d{8}$/'], 'replace' => ['boolean']]);
        $ticket = Ticket::where('page_id', $page->getKey())->where('number', $data['number'])->first();

        if (! $ticket) {
            return response()->json(['status' => 'not_found', 'message' => 'Tiket tidak ditemukan di acara ini.']);
        }

        $ticket = $cards->issue($ticket, TicketCardLog::SOURCE_STAFF, $scanner, null, replace: $request->boolean('replace'), checkIn: true);

        return response()->json([
            'status' => 'ok',
            'message' => 'ID card '.$ticket->holder_name.' siap dicetak.',
            'print' => $cards->render($page, $ticket),
        ]);
    }

    public function logout(Request $request, string $token): RedirectResponse
    {
        $scanner = $this->scanner($token);
        $request->session()->forget($this->sessionKey($scanner));

        return redirect()->route('scan.show', $token);
    }

    private function scanner(string $token): TicketScanner
    {
        $scanner = TicketScanner::with('page.branch.company')->where('token', $token)->firstOrFail();
        abort_unless($scanner->isActive(), 403, 'Link ini sudah dinonaktifkan oleh penyelenggara.');

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

    private function loggedOut(): JsonResponse
    {
        return response()->json(['status' => 'logged_out', 'message' => 'Sesi berakhir, silakan masukkan PIN lagi.'], 401);
    }
}

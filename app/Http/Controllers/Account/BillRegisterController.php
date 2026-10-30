<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\InvoiceRegisterLink;
use App\Models\User;
use App\Models\WebTermCondition;
use App\Rules\Turnstile;
use App\Services\Invoice\InvoiceCustomerJoin;
use App\Services\Referral\ReferralService;
use App\Services\Subscription\AppAccess;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Link pendaftaran customer Invoice (/tagihan/daftar/{token}).
 * Belum punya akun: daftar seperti biasa (email verifikasi) + otomatis jadi customer Invoice branch pemilik link,
 * lalu langsung masuk ke Tagihan Saya. Sudah login: cukup satu klik untuk bergabung.
 */
class BillRegisterController extends Controller
{
    public function __construct(private readonly InvoiceCustomerJoin $join, private readonly ReferralService $referrals)
    {
    }

    public function show(Request $request, string $token): View
    {
        $link = $this->link($token);

        if (! Auth::check()) {
            // "Masuk dulu" lalu kembali ke link ini.
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return view('bills.register', ['link' => $link, 'user' => $request->user()]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $link = $this->link($token);

        if (! $link->is_active) {
            return back()->with('error', 'Pendaftaran di '.$link->branch->name.' sedang ditutup.');
        }

        $user = $request->user();
        abort_if($user?->isPlatformTeam(), 403);

        if (! $user) {
            $request->merge(['handphone' => User::normalizePhone($request->input('handphone')), 'email' => strtolower(trim((string) $request->input('email')))]);
            $data = $request->validate([
                'name' => ['required', 'string', 'max:150'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
                'handphone' => ['required', 'regex:/^62[1-9][0-9]{8,12}$/', Rule::unique(User::class)],
                'password' => ['required', 'confirmed', Password::defaults()],
                'cf-turnstile-response' => [new Turnstile()],
                'terms' => ['accepted'],
            ], [
                'email.unique' => 'Email ini sudah punya akun. Silakan masuk dulu, lalu buka link ini lagi.',
                'handphone.unique' => 'Nomor WhatsApp ini sudah punya akun. Silakan masuk dulu, lalu buka link ini lagi.',
                'handphone.regex' => 'Nomor WhatsApp belum benar. Isi 9–13 digit tanpa awalan 0, contoh: 81234567890.',
                'terms.accepted' => 'Centang persetujuan Syarat & Ketentuan dan Kebijakan Privasi untuk melanjutkan.',
            ], ['name' => 'nama Anda', 'handphone' => 'nomor WhatsApp']);
        }

        // Akun (tabel users) + customer Invoice (tabel customers) jadi bersamaan, atau tidak sama sekali.
        $created = DB::transaction(function () use ($user, $link, $request, &$data) {
            $account = $user ?? $this->createUser($data, $request);
            $this->join->join($link->branch, $account);

            return $user ? null : $account;
        });

        if ($created) {
            event(new Registered($created)); // kirim email verifikasi, sama seperti daftar biasa
            Auth::login($created);
            $request->session()->regenerate();
        }

        $bills = route('bills.index', ['cabang' => $link->branch_id]);

        if (! $request->user()->hasVerifiedEmail()) {
            // Setelah klik link verifikasi di email, langsung ke Tagihan Saya cabang ini.
            $request->session()->put('url.intended', $bills);

            return redirect()->route('verification.notice')->with('status', 'Akun Anda sudah terdaftar sebagai customer '.$link->branch->name
                .'. Klik link verifikasi yang kami kirim ke email, lalu tagihan Anda langsung tampil.');
        }

        $request->session()->forget('url.intended');

        return redirect($bills)->with('success', 'Anda terdaftar sebagai customer '.$link->branch->name.'.');
    }

    /** Akun biasa (sama seperti daftar biasa): punya saldo & kode referral, bisa buka usaha sendiri kapan saja. */
    private function createUser(array $data, Request $request): User
    {
        $user = User::create(['name' => trim($data['name']), 'email' => $data['email'], 'handphone' => $data['handphone'], 'password' => Hash::make($data['password'])]);
        $user->forceFill(['status' => User::STATUS_ACTIVE, 'terms_accepted_at' => now(), 'terms_id' => WebTermCondition::current()?->getKey()])->save();
        $this->referrals->recordSignup($user, $request->session()->pull('referral_code'));

        return $user;
    }

    /** Link milik branch aktif yang paket Invoice-nya masih berjalan. */
    private function link(string $token): InvoiceRegisterLink
    {
        abort_unless(strlen($token) === 40, 404);

        return InvoiceRegisterLink::with('branch:id,name,company_id,status')
            ->where('token', $token)
            ->whereHas('branch', fn ($q) => $q->where('status', 'active'))
            ->whereIn('branch_id', AppAccess::activeBranches('invoices'))
            ->firstOrFail();
    }
}

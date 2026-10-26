<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WebTermCondition;
use App\Rules\Turnstile;
use App\Services\Referral\ReferralService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function __construct(private readonly ReferralService $referrals)
    {
    }

    public function create(Request $request): View
    {
        // Kode dari link ?ref= disimpan di sesi supaya tetap terbawa walau form gagal validasi atau daftar lewat Google.
        // Hanya kode yang benar-benar aktif yang disimpan, jadi link lain/rusak tidak menimpa kode yang sah.
        if ($request->filled('ref') && ($code = $this->referrals->findActiveCode((string) $request->query('ref')))) {
            $request->session()->put('referral_code', $code->code);
        }

        return view('auth.register', [
            'terms' => WebTermCondition::current(),
            'referralCode' => $this->referrals->findActiveCode($request->session()->get('referral_code')),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge(['handphone' => User::normalizePhone($request->input('handphone'))]);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'handphone' => ['required', 'regex:/^62[1-9][0-9]{8,12}$/', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'cf-turnstile-response' => [new Turnstile()],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => 'Centang persetujuan Syarat & Ketentuan dan Kebijakan Privasi untuk melanjutkan.',
            'handphone.regex' => 'Nomor WhatsApp belum benar. Isi 9–13 digit tanpa awalan 0, contoh: 81234567890.',
            'handphone.unique' => 'Nomor WhatsApp ini sudah terdaftar. Silakan masuk atau pakai nomor lain.',
        ]);

        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'handphone' => $request->handphone,
                'password' => Hash::make($request->password),
            ]);
            $user->forceFill(['terms_accepted_at' => now(), 'terms_id' => WebTermCondition::current()?->getKey()])->save();

            $this->referrals->recordSignup($user, $request->session()->pull('referral_code'));

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}

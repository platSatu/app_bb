<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\SchedulePortalLink;
use App\Models\User;
use App\Rules\Turnstile;
use App\Services\Referral\ReferralService;
use App\Services\Schedule\SchedulePortalService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use RuntimeException;

/**
 * Halaman link pendaftaran portal (/daftar/{token}).
 * Belum punya akun: buat akun sekaligus ajukan. Sudah masuk (owner/pengajar/orang tua): cukup ajukan.
 */
class JoinController extends Controller
{
    public function __construct(private readonly SchedulePortalService $portal, private readonly ReferralService $referrals)
    {
    }

    public function show(Request $request, string $token): View
    {
        $link = $this->link($token);

        if (! Auth::check()) {
            // Setelah masuk, kembali lagi ke link ini.
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return view('portal.join.show', ['link' => $link, 'user' => $request->user()]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $link = $this->link($token);

        if (! $link->is_active) {
            return back()->with('error', 'Pendaftaran di '.$link->branch->name.' sedang ditutup.');
        }

        $user = $request->user();
        abort_if($user?->isPlatformTeam(), 403);

        $isStudent = $link->type === SchedulePortalLink::TYPE_STUDENT;
        $forChild = $isStudent && $request->input('for') === 'child';

        $request->merge(['handphone' => User::normalizePhone($request->input('handphone')), 'email' => strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            ...($user ? [] : [
                'name' => ['required', 'string', 'max:150'],
                'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
                'handphone' => ['required', 'regex:/^62[1-9][0-9]{8,12}$/', Rule::unique(User::class)],
                'password' => ['required', 'confirmed', Password::defaults()],
                'cf-turnstile-response' => [new Turnstile()],
            ]),
            'for' => [Rule::requiredIf($isStudent), 'nullable', Rule::in(['self', 'child'])],
            'student_name' => [Rule::requiredIf($forChild), 'nullable', 'string', 'max:150'],
        ], [
            'email.unique' => 'Email ini sudah punya akun. Silakan masuk dulu, lalu buka link ini lagi.',
            'handphone.unique' => 'Nomor WhatsApp ini sudah punya akun. Silakan masuk dulu, lalu buka link ini lagi.',
            'handphone.regex' => 'Nomor WhatsApp belum benar. Isi 9–13 digit tanpa awalan 0, contoh: 81234567890.',
            'student_name.required' => 'Isi nama anak yang mau didaftarkan ya.',
        ], ['name' => 'nama Anda', 'handphone' => 'nomor WhatsApp', 'student_name' => 'nama anak']);

        try {
            $user = DB::transaction(function () use ($user, $data, $link, $forChild, $request) {
                $user ??= $this->createUser($data, $request);
                $name = $forChild ? trim($data['student_name']) : $user->name;
                $this->portal->request($link, $user, $name, $forChild ? $user->name : null);

                return $user;
            });
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if (! Auth::check()) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        $request->session()->forget('url.intended');

        return redirect()->route('dashboard')->with('success', 'Pendaftaran terkirim ke admin '.$link->branch->name.'. Kami kabari begitu disetujui, ya.');
    }

    /** Akun biasa (sama seperti daftar biasa): punya saldo & kode referral, bisa buka usaha sendiri kapan saja. */
    private function createUser(array $data, Request $request): User
    {
        $user = User::create(['name' => trim($data['name']), 'email' => $data['email'], 'handphone' => $data['handphone'], 'password' => $data['password']]);
        $this->referrals->recordSignup($user, $request->session()->pull('referral_code'));
        event(new Registered($user));

        return $user;
    }

    private function link(string $token): SchedulePortalLink
    {
        return SchedulePortalLink::with('branch:id,name,status')
            ->where('token', $token)
            ->whereHas('branch', fn ($q) => $q->where('status', 'active'))
            ->whereIn('branch_id', \App\Services\Subscription\AppAccess::activeBranches('schedules'))
            ->firstOrFail();
    }
}

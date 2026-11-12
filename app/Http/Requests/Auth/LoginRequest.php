<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Rules\Turnstile;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login dibatasi dua lapis supaya password tidak bisa ditebak terus-menerus:
 *  - per akun + IP: 5 kali salah -> tunggu 15 menit;
 *  - per akun dari IP mana pun: 20 kali salah -> tunggu 30 menit (menahan tebakan beramai-ramai dari banyak IP).
 * Setiap penguncian dicatat di log. Akun yang dikunci tetap bisa masuk lewat Google atau atur ulang password.
 */
class LoginRequest extends FormRequest
{
    private const LIMITS = [
        // [kunci, maks. salah, lama kunci (detik)]
        'ip' => [5, 900],
        'account' => [20, 1800],
    ];

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'cf-turnstile-response' => [new Turnstile()],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), self::LIMITS['ip'][1]);
            RateLimiter::hit($this->accountKey(), self::LIMITS['account'][1]);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->accountKey());

        if (! Auth::user()->isActive()) {
            Auth::guard('web')->logout();

            throw ValidationException::withMessages(['email' => User::INACTIVE_MESSAGE]);
        }
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        $key = match (true) {
            RateLimiter::tooManyAttempts($this->throttleKey(), self::LIMITS['ip'][0]) => $this->throttleKey(),
            RateLimiter::tooManyAttempts($this->accountKey(), self::LIMITS['account'][0]) => $this->accountKey(),
            default => null,
        };

        if (! $key) {
            return;
        }

        event(new Lockout($this));
        Log::warning('[LOGIN] Terlalu banyak password salah, login ditahan sementara', [
            'email' => Str::mask(Str::lower($this->string('email')), '*', 2, -6), 'ip' => $this->ip(), 'scope' => $key === $this->accountKey() ? 'akun' : 'akun+ip',
        ]);

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

    private function accountKey(): string
    {
        return 'login-account:'.sha1(Str::lower($this->string('email')));
    }
}

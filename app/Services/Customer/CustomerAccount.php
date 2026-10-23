<?php

namespace App\Services\Customer;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * "Jadikan User": customer dibuatkan akun login (status aktif, tipe user, email langsung terverifikasi).
 * Password tidak dibuat admin; customer menerima link "buat password" lewat email.
 */
class CustomerAccount
{
    /** @return array{0: User, 1: bool} [akun, baru dibuat] */
    public function grant(Customer $customer): array
    {
        $email = strtolower(trim((string) $customer->email));

        if ($email === '') {
            throw new RuntimeException('Isi email customer ini dulu. Akun login memakai email.');
        }

        if ($existing = User::where('email', $email)->first()) {
            return [$existing, false];
        }

        $user = DB::transaction(function () use ($customer, $email) {
            $phone = $customer->phone && ! User::where('handphone', $customer->phone)->exists() ? $customer->phone : null;
            $user = new User();
            $user->forceFill([
                'name' => $customer->name ?: Str::before($email, '@'),
                'email' => $email,
                'handphone' => $phone,
                'password' => Hash::make(Str::random(40)),
                'status' => User::STATUS_ACTIVE,
                'user_type' => User::TYPE_USER,
                'email_verified_at' => now(),
            ])->save();

            return $user;
        });

        try {
            Password::broker()->sendResetLink(['email' => $email]);
        } catch (Throwable $e) {
            report($e); // akun tetap jadi; customer bisa memakai "Lupa password" di halaman login
        }

        return [$user, true];
    }

    /** Email customer yang sudah punya akun login (untuk badge di daftar). */
    public static function emailsWithAccount(iterable $customers): array
    {
        $emails = collect($customers)->pluck('email')->filter()->map(fn ($e) => strtolower($e))->unique()->values();

        return $emails->isEmpty() ? [] : User::whereIn('email', $emails)->pluck('email')->map(fn ($e) => strtolower($e))->flip()->all();
    }
}

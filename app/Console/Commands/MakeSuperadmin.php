<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeSuperadmin extends Command
{
    protected $signature = 'user:superadmin {email : Email user yang dijadikan superadmin}';

    protected $description = 'Jadikan user yang sudah terdaftar sebagai superadmin aktif';

    public function handle(): int
    {
        $user = User::where('email', strtolower($this->argument('email')))->first();

        if (! $user) {
            $this->error('User dengan email tersebut tidak ditemukan. Daftar dulu lewat halaman register.');

            return self::FAILURE;
        }

        $user->forceFill([
            'user_type' => User::TYPE_SUPERADMIN,
            'status' => User::STATUS_ACTIVE,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $this->info("{$user->name} sekarang superadmin.");

        return self::SUCCESS;
    }
}

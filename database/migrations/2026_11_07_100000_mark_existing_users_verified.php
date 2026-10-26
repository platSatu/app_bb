<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Verifikasi email mulai diwajibkan untuk pendaftaran baru. User yang sudah ada sebelum ini
 * dianggap terverifikasi supaya tidak tiba-tiba terkunci saat login berikutnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Tidak dikembalikan: tidak bisa dibedakan mana yang terverifikasi karena migration ini.
    }
};

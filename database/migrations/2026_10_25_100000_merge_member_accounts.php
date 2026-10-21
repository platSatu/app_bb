<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Satu akun banyak peran: akun "member" (portal) menjadi akun biasa. Peran pengajar/murid dibaca dari data.
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('user_type', 'member')->update(['user_type' => 'user']);
    }

    public function down(): void
    {
    }
};

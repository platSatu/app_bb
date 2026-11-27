<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chatbot WhatsApp Jadwal tahap 3 (menu admin): PIN untuk aksi besar lewat WhatsApp
 * (setujui semua pengajuan, buat tagihan semua murid, kirim pengingat massal). Disimpan sebagai hash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('wa_pin')->nullable()->after('password'));
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('wa_pin'));
    }
};

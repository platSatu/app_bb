<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_chatbot_flows', function (Blueprint $table) {
            // NULL = fitur mati (bawaan untuk semua flow yang sudah ada, jadi tidak ada
            // pelanggan yang tiba-tiba kebagian sapaan tanpa memintanya).
            // Diisi angka = jumlah menit diam sebelum kontak disapa, lalu selama itu lagi
            // sebelum percakapan ditutup. Lihat App\Console\Commands\NudgeIdleBotSessions.
            $table->unsignedSmallInteger('nudge_minutes')->nullable()->after('timeout_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('wa_chatbot_flows', function (Blueprint $table) {
            $table->dropColumn('nudge_minutes');
        });
    }
};

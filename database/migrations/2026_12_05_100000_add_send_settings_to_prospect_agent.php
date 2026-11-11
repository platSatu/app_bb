<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent Prospek: pengaturan kirim otomatis (tombol aktif, hari, jam, jumlah per jam & per hari) dan kontak hasil
 * import Excel (source = import). Default NONAKTIF: tidak ada email terkirim sebelum superadmin menyalakannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospect_agent_settings', function (Blueprint $table) {
            $table->boolean('send_enabled')->default(false)->after('min_score');
            $table->json('send_days')->nullable()->after('send_enabled');                      // [1..7], 1 = Senin
            $table->unsignedTinyInteger('send_start')->default(8)->after('send_days');           // jam WIB
            $table->unsignedTinyInteger('send_end')->default(18)->after('send_start');
            $table->unsignedSmallInteger('send_per_hour')->default(20)->after('send_end');
            $table->unsignedSmallInteger('send_per_day')->default(200)->after('send_per_hour');
            $table->boolean('auto_approve_import')->default(false)->after('send_per_day');     // kontak import langsung dikirim pakai template
        });

        Schema::table('prospects', function (Blueprint $table) {
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropIndex(['email']);
        });

        Schema::table('prospect_agent_settings', function (Blueprint $table) {
            $table->dropColumn(['send_enabled', 'send_days', 'send_start', 'send_end', 'send_per_hour', 'send_per_day', 'auto_approve_import']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sakelar notifikasi WhatsApp per branch: {"invoice": true, "ticket": false, "schedule": true}.
 * Kosong = semua mati (bawaan). Hanya berlaku selama paket WhatsApp branch aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_branches', function (Blueprint $table) {
            $table->json('wa_notify')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('user_branches', function (Blueprint $table) {
            $table->dropColumn('wa_notify');
        });
    }
};

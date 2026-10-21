<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Bukti pembeli menyetujui Syarat & Ketentuan page tiket saat memesan. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_orders', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_orders', function (Blueprint $table) {
            $table->dropColumn('terms_accepted_at');
        });
    }
};

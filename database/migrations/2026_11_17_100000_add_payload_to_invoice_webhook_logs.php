<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simpan isi webhook yang dikirim ke website customer + jawaban server mereka,
 * supaya di detail invoice bisa dibandingkan dengan callback dari Duitku. Log lama tetap (kolom kosong).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_webhook_logs', function (Blueprint $table) {
            $table->text('payload')->nullable()->after('http_status');
            $table->text('response_body')->nullable()->after('payload');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_webhook_logs', function (Blueprint $table) {
            $table->dropColumn(['payload', 'response_body']);
        });
    }
};

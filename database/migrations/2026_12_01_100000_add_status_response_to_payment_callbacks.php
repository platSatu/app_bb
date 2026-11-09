<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jawaban asli Duitku saat pembayaran dicek ulang (transactionStatus: amount, fee, statusCode, ...), disimpan
 * di baris callback yang sama supaya superadmin bisa membandingkan callback & jawaban Duitku (Sett & Lap › Log).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_callbacks', function (Blueprint $table) {
            $table->json('status_response')->nullable()->after('payload');
        });
    }

    public function down(): void
    {
        Schema::table('payment_callbacks', fn (Blueprint $table) => $table->dropColumn('status_response'));
    }
};

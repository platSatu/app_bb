<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fee yang dipotong Duitku dari uang masuk (MDR yang ditanggung bizbos, saat ini QRIS), dicatat per transaksi
 * saat uang masuk (WalletService::creditPayment). Mutasi memakai ini supaya Kredit = yang benar-benar masuk Duitku.
 * Transaksi lama dibiarkan NULL (tidak diisi ulang).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->unsignedInteger('duitku_fee')->nullable()->after('method_fee');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', fn (Blueprint $table) => $table->dropColumn('duitku_fee'));
    }
};

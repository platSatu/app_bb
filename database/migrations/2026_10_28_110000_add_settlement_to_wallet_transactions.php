<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uang dari Duitku baru bisa ditarik setelah settlement (tergantung metode bayar).
 * available_at kosong = langsung bisa ditarik, jadi semua data lama tetap seperti sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->string('payment_method', 20)->nullable()->after('description');
            $table->timestamp('available_at')->nullable()->after('payment_method');
            $table->index(['wallet_id', 'available_at']);
        });

        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('settlement_default_days')->default(2)->after('max_deposit');
            $table->json('settlement_methods')->nullable()->after('settlement_default_days');
            $table->json('settlement_holidays')->nullable()->after('settlement_methods');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex(['wallet_id', 'available_at']);
            $table->dropColumn(['payment_method', 'available_at']);
        });

        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            $table->dropColumn(['settlement_default_days', 'settlement_methods', 'settlement_holidays']);
        });
    }
};

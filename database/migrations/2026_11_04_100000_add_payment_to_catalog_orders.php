<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembayaran katalog (tab Pembayaran di page katalog): transfer bank ke rekening owner, atau
 * bayar online lewat Duitku bizbos (uangnya masuk saldo owner seperti penjualan tiket).
 * payment_type kosong = pesanan lama / tanpa pembayaran (dikonfirmasi langsung oleh penjual).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_orders', function (Blueprint $table) {
            $table->string('payment_type', 10)->nullable()->after('total');
            // Link halaman pesanan pembeli (juga returnUrl Duitku): token acak, tidak bisa ditebak.
            $table->string('access_token', 48)->nullable()->unique()->after('payment_type');
            $table->text('payment_url')->nullable()->after('access_token');
            $table->string('provider_reference', 100)->nullable()->after('payment_url');
            $table->string('payment_method', 20)->nullable()->after('provider_reference');
            $table->string('payment_note')->nullable()->after('payment_method');
            $table->timestamp('expires_at')->nullable()->after('payment_note');
            $table->timestamp('paid_at')->nullable()->after('expires_at');

            // Untuk job kedaluwarsa: pesanan online yang belum dibayar & lewat batas.
            $table->index(['payment_type', 'status', 'expires_at'], 'catalog_orders_payment_index');
        });
    }

    public function down(): void
    {
        Schema::table('catalog_orders', function (Blueprint $table) {
            $table->dropIndex('catalog_orders_payment_index');
            $table->dropUnique(['access_token']);
            $table->dropColumn(['payment_type', 'access_token', 'payment_url', 'provider_reference', 'payment_method', 'payment_note', 'expires_at', 'paid_at']);
        });
    }
};

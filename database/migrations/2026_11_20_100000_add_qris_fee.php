<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Potongan biaya QRIS (29 Okt 2026).
 *
 * Biaya transaksi QRIS di Duitku dibebankan ke merchant (bizbos), tidak bisa ke customer.
 * Supaya tidak rugi, setiap pembayaran yang dibayar dengan QRIS dipotong persentase ini
 * saat uangnya masuk ke saldo (top up, invoice, tagihan, tiket, katalog). Hanya MENAMBAH
 * kolom; data & alur lama tidak berubah.
 *
 *   payment_gateway_settings.qris_fee_percent  → persen potongan (default 0,7)
 *   payment_gateway_settings.qris_fee_methods  → kode metode Duitku yang dihitung QRIS (null = SP, NQ, SQ)
 *   wallet_transactions.method_fee             → potongan QRIS di transaksi saldo itu (0 = tidak ada)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_gateway_settings', 'qris_fee_percent')) {
                $table->decimal('qris_fee_percent', 5, 2)->default(0.70);
                $table->json('qris_fee_methods')->nullable();
            }
        });

        Schema::table('wallet_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('wallet_transactions', 'method_fee')) {
                $table->unsignedBigInteger('method_fee')->default(0)->after('amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', fn (Blueprint $table) => $table->dropColumn('method_fee'));
        Schema::table('payment_gateway_settings', fn (Blueprint $table) => $table->dropColumn(['qris_fee_percent', 'qris_fee_methods']));
    }
};

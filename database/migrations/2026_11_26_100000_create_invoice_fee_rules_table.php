<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Biaya transaksi Invoice per customer (branch) & per metode bayar — 5 Nov 2026.
 *
 * - Customer TANPA aturan: tetap memakai Biaya Invoice global + potongan QRIS (perilaku lama, tidak berubah).
 * - Ditanggung customer ("merchant"): boleh per metode (flat / persen), dihitung saat lunas dari metode
 *   yang dilaporkan Duitku, dipotong dari uang yang masuk ke saldo. Baris payment_method NULL = semua metode lain.
 * - Ditanggung pembayar ("payer"): satu aturan untuk semua metode, ditambahkan ke total sebelum ke Duitku
 *   (metode baru dipilih pembayar di halaman Duitku, jadi tidak bisa berbeda per metode).
 *
 * invoice_payments.service_fee / fee_bearer: snapshot biaya pembayar saat order Duitku dibuat, supaya
 * perubahan aturan tidak mengubah tagihan yang sudah berjalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_fee_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->string('payment_method', 10)->nullable(); // NULL = semua metode
            $table->string('fee_type', 10);                   // flat | percent
            $table->decimal('fee_value', 12, 2);
            $table->string('bearer', 10);                     // merchant | payer
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'payment_method']);
        });

        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('service_fee')->nullable()->after('late_fee');
            $table->string('fee_bearer', 10)->nullable()->after('service_fee');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropColumn(['service_fee', 'fee_bearer']);
        });

        Schema::dropIfExists('invoice_fee_rules');
    }
};

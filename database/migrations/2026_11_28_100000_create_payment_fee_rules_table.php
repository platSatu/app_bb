<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Biaya pembayaran per pelanggan bizbos & per metode, diatur di Superadmin › Payment Gateway.
 * Berlaku untuk SEMUA aplikasi yang menerima pembayaran Duitku (invoice, API, Google Form, tiket,
 * toko online, top up). Pelanggan tanpa aturan memakai default (potongan QRIS di setting Payment Gateway).
 *
 * wallet_transactions.paid_amount: nominal bruto dari Duitku, supaya pendapatan bizbos terbaca dari satu tempat.
 *
 * Menggantikan invoice_fee_rules (hanya pernah ada di staging): tabel itu & kolom "ditanggung pembayar"
 * di invoice_payments dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('invoice_fee_rules');

        if (Schema::hasColumn('invoice_payments', 'service_fee')) {
            Schema::table('invoice_payments', fn (Blueprint $table) => $table->dropColumn(['service_fee', 'fee_bearer']));
        }

        Schema::create('payment_fee_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete(); // pelanggan bizbos (pemilik saldo)
            $table->string('payment_method', 10)->nullable();                 // NULL = semua metode
            $table->string('fee_type', 10);                                   // flat | percent
            $table->decimal('fee_value', 12, 2);
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'payment_method']);
        });

        // Yang dibayar pembeli di Duitku (bruto) per uang masuk; bruto - amount = pendapatan bizbos (fee + potongan).
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('paid_amount')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('wallet_transactions', fn (Blueprint $table) => $table->dropColumn('paid_amount'));
        Schema::dropIfExists('payment_fee_rules');
    }
};

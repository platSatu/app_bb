<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembayaran gabungan dari dashboard Tagihan: 1 order Duitku untuk beberapa invoice sekaligus.
 * Tabel invoice & invoice_payments lama tidak diubah; bayar satu per satu lewat link tetap jalan seperti biasa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_checkouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->string('merchant_order_id', 50)->unique();
            $table->string('provider_reference', 100)->nullable();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('late_fee')->default(0);
            $table->unsignedBigInteger('fee')->default(0);
            $table->unsignedBigInteger('net_amount')->default(0);
            $table->string('payment_url', 500)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('payment_method', 20)->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('invoice_checkout_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('checkout_id')->constrained('invoice_checkouts')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('late_fee')->default(0);
            $table->unsignedBigInteger('total');
            // Diisi saat lunas: paid, atau refund_needed kalau invoice-nya ternyata sudah lunas/dibatalkan lebih dulu.
            $table->string('result', 20)->nullable();
            $table->timestamps();

            $table->unique(['checkout_id', 'invoice_id']);
            $table->index('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_checkout_items');
        Schema::dropIfExists('invoice_checkouts');
    }
};

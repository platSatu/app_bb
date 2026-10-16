<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Invoice: tagihan ke customer, dari dashboard (kategori > periode) atau lewat API.
| Semua dibayar di halaman bizbos -> Duitku bizbos -> saldo owner (dipotong biaya layanan).
*/
return new class extends Migration
{
    public function up(): void
    {
        // Pengaturan platform (satu baris), diisi superadmin.
        Schema::create('invoice_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->decimal('fee_percent', 5, 2)->default(0);
            $table->unsignedInteger('fee_flat')->default(0);
            $table->unsignedBigInteger('min_amount')->default(10000);
            $table->unsignedBigInteger('max_amount')->default(100000000);
            $table->unsignedSmallInteger('api_expiry_minutes')->default(1440);
            $table->timestamps();
        });

        Schema::create('invoice_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('prefix', 10)->default('INV');
            // none | flat | percent | percent_flat
            $table->string('late_fee_mode', 20)->default('none');
            $table->unsignedInteger('late_fee_flat')->default(0);
            $table->decimal('late_fee_percent', 5, 2)->default(0);
            $table->string('late_fee_period', 10)->default('day');
            $table->unsignedSmallInteger('late_fee_percent_days')->nullable();
            $table->boolean('email_enabled')->default(true);
            $table->json('reminder_days')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['branch_id', 'name']);
        });

        // Customer (Buku Kontak) yang berlangganan kategori, boleh dengan nominal khusus.
        Schema::create('invoice_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')->constrained('invoice_categories')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->unsignedBigInteger('amount')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['category_id', 'customer_id']);
        });

        // Satu periode tagihan, mis. "Iuran Oktober 2026".
        Schema::create('invoice_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('invoice_categories')->cascadeOnDelete();
            $table->string('name', 150);
            $table->unsignedBigInteger('amount');
            $table->date('due_date');
            $table->boolean('use_late_fee')->default(true);
            $table->string('status', 20)->default('active');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('invoice_api_clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->unique()->constrained('user_branches')->cascadeOnDelete();
            $table->string('api_key', 64)->unique();
            $table->text('secret');
            $table->string('webhook_url', 500)->nullable();
            $table->text('webhook_secret');
            $table->json('allowed_origins')->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_used_at')->nullable();
            $table->timestamps();
        });

        // Satu invoice = satu tagihan untuk satu customer (yang dibayar).
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained('user_companies')->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('invoice_categories')->nullOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained('invoice_batches')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignUuid('api_client_id')->nullable()->constrained('invoice_api_clients')->nullOnDelete();
            $table->string('source', 20)->default('dashboard');
            $table->string('number', 30)->unique();
            $table->char('token', 48)->unique();
            $table->string('external_id', 100)->nullable();
            $table->string('description', 255);
            $table->string('customer_name', 150);
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 20)->nullable();
            $table->unsignedBigInteger('amount');
            $table->boolean('apply_late_fee')->default(false);
            // Diisi saat lunas: denda yang dibayar, total, biaya layanan bizbos, dan yang masuk ke saldo owner.
            $table->unsignedBigInteger('late_fee')->default(0);
            $table->unsignedBigInteger('paid_amount')->default(0);
            $table->unsignedBigInteger('fee')->default(0);
            $table->unsignedBigInteger('net_amount')->default(0);
            $table->date('due_date')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->string('return_url', 500)->nullable();
            $table->string('status', 20)->default('unpaid');
            $table->string('payment_method', 20)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason', 255)->nullable();
            $table->dateTime('emailed_at')->nullable();
            $table->timestamps();

            $table->unique(['api_client_id', 'external_id']);
            $table->index(['branch_id', 'status', 'created_at']);
            $table->index(['status', 'expires_at']);
        });

        // Setiap klik "Bayar" = satu order Duitku. Invoice bisa dibayar ulang kalau order sebelumnya kedaluwarsa.
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->string('merchant_order_id', 50)->unique();
            $table->string('provider_reference', 100)->nullable();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('late_fee')->default(0);
            $table->string('payment_url', 500)->nullable();
            // pending | paid | expired | failed | refund_needed
            $table->string('status', 20)->default('pending');
            $table->string('payment_method', 20)->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });

        Schema::create('invoice_webhook_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('api_client_id')->constrained('invoice_api_clients')->cascadeOnDelete();
            $table->foreignUuid('invoice_id')->nullable()->constrained('invoices')->cascadeOnDelete();
            $table->string('event', 30);
            $table->unsignedTinyInteger('attempt')->default(1);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();

            $table->index(['api_client_id', 'created_at']);
        });

        Schema::create('invoice_reminder_logs', function (Blueprint $table) {
            $table->foreignUuid('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->unsignedSmallInteger('days_before');
            $table->dateTime('sent_at');

            $table->primary(['invoice_id', 'days_before']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_reminder_logs');
        Schema::dropIfExists('invoice_webhook_logs');
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('invoice_api_clients');
        Schema::dropIfExists('invoice_batches');
        Schema::dropIfExists('invoice_subscriptions');
        Schema::dropIfExists('invoice_categories');
        Schema::dropIfExists('invoice_settings');
    }
};

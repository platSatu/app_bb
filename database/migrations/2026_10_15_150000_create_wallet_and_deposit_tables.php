<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nominal uang disimpan sebagai rupiah bulat (bigint), bukan float.
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('balance')->default(0);
            $table->timestamps();
        });

        // Buku besar: hanya ditambah, tidak pernah diubah/dihapus.
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 30);
            $table->string('direction', 6);
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('balance_before');
            $table->unsignedBigInteger('balance_after');
            $table->string('reference_type', 50)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->string('description')->nullable();
            $table->string('idempotency_key', 100)->unique();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['wallet_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('deposits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->string('reference_number', 40)->unique();
            $table->unsignedBigInteger('amount');
            $table->string('status', 20)->default('pending')->index();
            $table->string('provider', 20)->default('duitku');
            $table->string('provider_reference', 100)->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->text('payment_url')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        // Log setiap callback dari payment gateway (valid maupun tidak) untuk audit.
        Schema::create('payment_callbacks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider', 20);
            $table->string('merchant_order_id', 60)->nullable()->index();
            $table->json('payload');
            $table->boolean('signature_valid')->default(false);
            $table->string('result', 30)->nullable();
            $table->string('message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('payment_gateway_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider', 20)->unique();
            $table->string('mode', 20)->default('sandbox');
            $table->string('sandbox_merchant_code', 50)->nullable();
            $table->text('sandbox_api_key')->nullable();
            $table->string('production_merchant_code', 50)->nullable();
            $table->text('production_api_key')->nullable();
            $table->unsignedInteger('expiry_minutes')->default(60);
            $table->unsignedBigInteger('min_deposit')->default(10000);
            $table->unsignedBigInteger('max_deposit')->default(100000000);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_settings');
        Schema::dropIfExists('payment_callbacks');
        Schema::dropIfExists('deposits');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};

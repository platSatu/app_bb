<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tarik saldo lewat Duitku Disbursement (produk Duitku yang berbeda dari payment gateway).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disbursement_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('mode', 20)->default('sandbox');
            $table->string('sandbox_user_id', 50)->nullable();
            $table->string('sandbox_email')->nullable();
            $table->text('sandbox_secret_key')->nullable();
            $table->string('production_user_id', 50)->nullable();
            $table->string('production_email')->nullable();
            $table->text('production_secret_key')->nullable();
            $table->unsignedBigInteger('withdrawal_fee')->default(0);
            $table->unsignedBigInteger('min_amount')->default(50000);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('bank_code', 10);
            $table->string('bank_name', 100);
            // Nomor rekening terenkripsi; hash untuk cek duplikat, 4 digit terakhir untuk tampilan.
            $table->text('account_number');
            $table->char('account_hash', 64)->index();
            $table->string('account_last4', 4);
            $table->string('account_name', 150);
            $table->string('status', 20)->default('pending_review');
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('review_note', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'bank_accounts_user_status_index');
        });

        Schema::create('wallet_withdrawals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('bank_account_id')->constrained('bank_accounts')->restrictOnDelete();
            $table->string('reference', 20)->unique();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('fee')->default(0);
            $table->unsignedBigInteger('net_amount');
            // Salinan rekening saat diajukan.
            $table->string('bank_code', 10);
            $table->string('bank_name', 100);
            $table->string('account_last4', 4);
            $table->string('account_name', 150);
            $table->string('status', 20)->default('pending');
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();
            $table->string('disburse_id', 100)->nullable();
            $table->string('cust_ref_number', 20)->nullable();
            $table->json('duitku_response')->nullable();
            $table->text('failure_reason')->nullable();
            $table->dateTime('held_at')->nullable();
            $table->dateTime('refunded_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'wallet_withdrawals_status_index');
            $table->index(['user_id', 'created_at'], 'wallet_withdrawals_user_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_withdrawals');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('disbursement_settings');
    }
};

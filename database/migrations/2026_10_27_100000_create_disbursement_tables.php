<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Disbursement: kirim dana dari saldo branch ke banyak rekening penerima (dashboard atau API).
 * Sengaja terpisah dari tarik saldo (bank_accounts / wallet_withdrawals) supaya alur tarik saldo
 * owner tetap seperti semula.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disbursement_recipients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->restrictOnDelete();
            $table->string('external_id', 100)->nullable();
            $table->string('label', 150)->nullable();
            $table->string('bank_code', 10);
            $table->string('bank_name', 100);
            // Nomor rekening terenkripsi; hash untuk cek duplikat, 4 digit terakhir untuk tampilan.
            $table->text('account_number');
            $table->char('account_hash', 64);
            $table->string('account_last4', 4);
            $table->string('account_name', 150);
            $table->string('status', 20)->default('active');
            $table->string('source', 20)->default('dashboard');
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'account_hash']);
            $table->unique(['branch_id', 'external_id']);
        });

        Schema::create('disbursements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('recipient_id')->constrained('disbursement_recipients')->restrictOnDelete();
            $table->foreignUuid('api_client_id')->nullable()->constrained('invoice_api_clients')->nullOnDelete();
            $table->string('source', 20)->default('dashboard');
            $table->string('external_id', 100)->nullable();
            $table->string('reference', 20)->unique();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('fee')->default(0);
            $table->unsignedBigInteger('net_amount');
            // Salinan rekening saat dikirim.
            $table->string('bank_code', 10);
            $table->string('bank_name', 100);
            $table->string('account_last4', 4);
            $table->string('account_name', 150);
            $table->string('description', 255)->nullable();
            $table->string('status', 20)->default('pending');
            $table->string('disburse_id', 100)->nullable();
            $table->string('cust_ref_number', 20)->nullable();
            $table->json('duitku_response')->nullable();
            $table->text('failure_reason')->nullable();
            $table->dateTime('held_at')->nullable();
            $table->dateTime('refunded_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['branch_id', 'external_id']);
            $table->index(['branch_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::table('invoice_api_clients', function (Blueprint $table) {
            $table->boolean('payout_enabled')->default(false)->after('is_active');
            $table->unsignedBigInteger('payout_max_amount')->nullable()->after('payout_enabled');
            $table->unsignedBigInteger('payout_daily_limit')->nullable()->after('payout_max_amount');
        });

        Schema::table('invoice_webhook_logs', function (Blueprint $table) {
            $table->foreignUuid('disbursement_id')->nullable()->after('invoice_id')->constrained('disbursements')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_webhook_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('disbursement_id');
        });

        Schema::table('invoice_api_clients', function (Blueprint $table) {
            $table->dropColumn(['payout_enabled', 'payout_max_amount', 'payout_daily_limit']);
        });

        Schema::dropIfExists('disbursements');
        Schema::dropIfExists('disbursement_recipients');
    }
};

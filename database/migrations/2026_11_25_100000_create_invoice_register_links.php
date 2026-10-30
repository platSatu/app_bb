<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link pendaftaran customer Invoice per branch (/tagihan/daftar/{token}): daftar akun + otomatis jadi customer Invoice branch itu.
 * Plus indeks untuk query yang sering: tagihan per email pembayar, dan invoice lewat jatuh tempo (scheduler).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_register_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->unique()->constrained('user_branches')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index(['app_key', 'email']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->index(['status', 'expires_at', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['status', 'expires_at', 'due_date']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['app_key', 'email']);
        });

        Schema::dropIfExists('invoice_register_links');
    }
};

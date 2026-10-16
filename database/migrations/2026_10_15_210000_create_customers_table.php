<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Buku kontak per branch. company_id disimpan juga untuk gabungan semua branch & penyaringan cepat.
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('user_companies')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->string('name', 150)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('source')->nullable();
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at');
            $table->timestamps();

            // NULL boleh banyak; nomor/email yang sama hanya sekali per branch.
            $table->unique(['branch_id', 'phone']);
            $table->unique(['branch_id', 'email']);
            $table->index(['company_id', 'last_seen_at']);
        });

        Schema::table('page_form_fields', function (Blueprint $table) {
            $table->string('contact_field', 10)->nullable()->after('is_required');
        });

        Schema::table('page_submissions', function (Blueprint $table) {
            $table->foreignUuid('customer_id')->nullable()->after('company_id')->constrained('customers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('page_submissions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::table('page_form_fields', function (Blueprint $table) {
            $table->dropColumn('contact_field');
        });

        Schema::dropIfExists('customers');
    }
};

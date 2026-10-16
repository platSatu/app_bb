<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kode mengenali jenis page dari template, bukan dari nama yang bisa diganti superadmin.
        Schema::table('page_types', function (Blueprint $table) {
            $table->string('template', 30)->default('registration')->after('slug')->index();
        });

        Schema::create('page_form_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_category_id')->constrained('page_categories')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('label', 150);
            $table->string('placeholder', 150)->nullable();
            $table->string('help_text')->nullable();
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['page_category_id', 'sort_order']);
        });

        Schema::create('page_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_category_id')->constrained('page_categories')->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained('user_companies')->cascadeOnDelete();
            $table->string('reference', 20)->unique();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['page_category_id', 'created_at']);
            $table->index(['company_id', 'created_at']);
        });

        // label & type disalin saat submit, supaya data lama tetap utuh walau field diubah/dihapus.
        Schema::create('page_submission_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_submission_id')->constrained('page_submissions')->cascadeOnDelete();
            $table->foreignUuid('page_form_field_id')->nullable()->constrained('page_form_fields')->nullOnDelete();
            $table->string('label', 150);
            $table->string('type', 20);
            $table->text('value')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name', 150)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_submission_answers');
        Schema::dropIfExists('page_submissions');
        Schema::dropIfExists('page_form_fields');

        Schema::table('page_types', function (Blueprint $table) {
            $table->dropColumn('template');
        });
    }
};

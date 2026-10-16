<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Template Quiz. page_id = page_categories, jadi kepemilikan tetap page -> branch -> company.
return new class extends Migration
{
    public function up(): void
    {
        // Pengaturan per template (mis. quiz: batas waktu, boleh mengulang).
        Schema::table('page_categories', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('status');
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained('page_categories')->cascadeOnDelete();
            $table->string('type', 20);
            $table->text('question');
            // Kunci jawaban untuk tipe text (boleh lebih dari satu). Tidak pernah dikirim ke browser.
            $table->json('answer_keys')->nullable();
            $table->unsignedSmallInteger('points')->default(10);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['page_id', 'sort_order'], 'quiz_questions_order_index');
        });

        Schema::create('quiz_options', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('question_id')->constrained('quiz_questions')->cascadeOnDelete();
            $table->string('label');
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['question_id', 'sort_order'], 'quiz_options_order_index');
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained('page_categories')->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained('user_companies')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('reference', 20)->unique();
            $table->string('name', 150);
            $table->string('phone', 20);
            $table->string('email')->nullable();
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('max_score')->default(0);
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->boolean('is_late')->default(false);
            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['page_id', 'created_at'], 'quiz_attempts_page_index');
            $table->index(['page_id', 'phone'], 'quiz_attempts_phone_index');
        });

        // Soal & jawaban disalin, supaya hasil lama tetap utuh walau soal diubah/dihapus.
        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('attempt_id')->constrained('quiz_attempts')->cascadeOnDelete();
            $table->foreignUuid('question_id')->nullable()->constrained('quiz_questions')->nullOnDelete();
            $table->string('type', 20);
            $table->text('question');
            $table->text('answer')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->unsignedSmallInteger('points')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_answers');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_options');
        Schema::dropIfExists('quiz_questions');

        Schema::table('page_categories', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AI = fasilitas bizbos: superadmin menyimpan penyedia & kunci API, user tinggal pakai.
        Schema::create('ai_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('driver', 20);
            $table->string('model', 100);
            $table->string('base_url')->nullable();
            $table->text('api_key');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('ai_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedSmallInteger('daily_quota')->default(20);
            $table->timestamps();
        });

        Schema::create('ai_usages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'date'], 'ai_usages_user_date_unique');
        });

        // Isi landing page per section; data = isian section (teks biasa, selalu di-escape saat tampil).
        Schema::create('landing_sections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained('page_categories')->cascadeOnDelete();
            $table->string('type', 20);
            $table->json('data');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['page_id', 'sort_order'], 'landing_sections_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_sections');
        Schema::dropIfExists('ai_usages');
        Schema::dropIfExists('ai_settings');
        Schema::dropIfExists('ai_providers');
    }
};

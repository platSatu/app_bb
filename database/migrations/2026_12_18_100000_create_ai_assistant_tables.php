<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Asisten AI superadmin: percakapan per user (hanya pemiliknya yang bisa membuka).
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 150);
            $table->string('model', 100)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'updated_at'], 'ai_conversations_user_updated_index');
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();
            $table->string('role', 20);
            $table->longText('content');
            $table->string('model', 100)->nullable();
            $table->unsignedInteger('input_tokens')->nullable();
            $table->unsignedInteger('output_tokens')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            // done = jawaban lengkap, stopped = dihentikan user / koneksi putus (isi sebagian tetap disimpan).
            $table->string('status', 20)->default('done');
            $table->timestamps();

            $table->index(['conversation_id', 'created_at'], 'ai_messages_conversation_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};

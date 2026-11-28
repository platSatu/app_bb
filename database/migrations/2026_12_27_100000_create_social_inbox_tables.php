<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tools › Inbox: DM & komentar Instagram, Facebook Page, dan komentar YouTube milik akun resmi bizbos.
 * Terpisah dari tabel WhatsApp (wa_*): WhatsApp di Inbox hanya dibaca dari tabel lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_inbox_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // off = AI tidak dipakai; suggest = AI hanya membuat draf; auto = AI membalas sendiri pertanyaan yang aman.
            $table->string('ai_mode', 10)->default('off');
            $table->json('ai_platforms')->nullable();
            $table->text('ai_instructions')->nullable();
            $table->unsignedSmallInteger('ai_max_per_hour')->default(20);
            // Token verifikasi webhook Meta (diisi juga di dashboard Meta). Bukan rahasia login, tapi tetap disimpan terenkripsi.
            $table->text('webhook_verify_token')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('social_threads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('social_account_id')->constrained('social_accounts')->cascadeOnDelete();
            $table->string('kind', 10); // dm | comment
            // dm: ID orang di platform (PSID / IGSID). comment: ID komentar utama (YouTube: ID comment thread).
            $table->string('external_id', 191);
            $table->string('contact_id', 191)->nullable();
            $table->string('contact_name', 191)->nullable();
            $table->string('contact_handle', 191)->nullable();
            $table->string('context_id', 191)->nullable(); // ID post / media / video untuk komentar
            $table->string('context_url', 500)->nullable();
            $table->json('meta')->nullable();
            $table->string('status', 10)->default('open'); // open | done
            $table->string('handler', 10)->default('ai'); // ai | admin (admin = AI berhenti di percakapan ini)
            $table->boolean('needs_human')->default(false);
            $table->unsignedInteger('unread_count')->default(0);
            $table->string('last_preview', 255)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamps();

            $table->unique(['social_account_id', 'kind', 'external_id']);
            $table->index('last_message_at');
            $table->index(['status', 'last_message_at']);
        });

        Schema::create('social_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('social_thread_id')->constrained('social_threads')->cascadeOnDelete();
            $table->string('external_id', 191)->nullable();
            $table->string('direction', 3); // in | out
            $table->string('author_name', 191)->nullable();
            $table->text('text')->nullable();
            $table->json('attachments')->nullable();
            $table->string('via', 10); // platform | admin | ai
            $table->foreignUuid('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 10)->default('sent'); // sent | failed
            $table->string('error', 300)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['social_thread_id', 'external_id']);
            $table->index(['social_thread_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_messages');
        Schema::dropIfExists('social_threads');
        Schema::dropIfExists('social_inbox_settings');
    }
};

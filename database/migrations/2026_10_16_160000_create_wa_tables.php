<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| WhatsApp v2. Tabel dibaca/ditulis oleh service Go (go_bizbos) dan dibaca bizbos.
| Device per branch; sesi WhatsApp (kunci enkripsi) tetap di SQLite milik go_bizbos.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('phone', 20)->nullable();
            $table->string('jid', 100)->nullable()->index();
            $table->string('push_name', 100)->nullable();
            $table->string('platform', 50)->nullable();
            // disconnected | connecting | pairing | connected | logged_out | banned | replaced
            $table->string('status', 20)->default('disconnected')->index();
            $table->string('status_reason')->nullable();
            // 0 = ikut batas default server
            $table->unsignedInteger('daily_cap')->default(0);
            $table->unsignedInteger('sent_today')->default(0);
            $table->date('sent_date')->nullable();
            $table->timestamp('warmup_started_at')->nullable();
            $table->timestamp('banned_until')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'name']);
        });

        Schema::create('wa_chats', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->foreignUuid('device_id')->constrained('wa_devices')->cascadeOnDelete();
            $table->string('chat_jid', 100);
            $table->string('phone', 20)->nullable()->index();
            $table->string('name', 191)->nullable();
            $table->boolean('is_group')->default(false);
            $table->unsignedInteger('unread_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'chat_jid']);
            $table->index(['device_id', 'last_message_at']);
        });

        Schema::create('wa_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->foreignUuid('device_id')->constrained('wa_devices')->cascadeOnDelete();
            $table->foreignUuid('chat_id')->constrained('wa_chats')->cascadeOnDelete();
            $table->string('wa_id', 128);
            $table->boolean('from_me')->default(false);
            $table->string('sender_jid', 100)->nullable();
            $table->string('sender_name', 191)->nullable();
            // text | image | video | audio | document | sticker | location | contact | reaction | poll | revoked
            $table->string('type', 20);
            $table->mediumText('body')->nullable();
            $table->string('quoted_wa_id', 128)->nullable();
            // Kunci unduh media (rahasia), jangan pernah dikirim ke browser.
            $table->json('media_meta')->nullable();
            $table->string('media_path')->nullable();
            $table->string('media_mime', 100)->nullable();
            $table->string('media_name', 191)->nullable();
            $table->unsignedBigInteger('media_size')->nullable();
            // pending | sent | delivered | read | played | failed
            $table->string('status', 20)->default('sent');
            $table->string('error')->nullable();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['chat_id', 'wa_id']);
            $table->index(['device_id', 'wa_id']);
            $table->index(['chat_id', 'sent_at']);
        });

        Schema::create('wa_uploads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->string('path');
            $table->string('mime', 100);
            $table->string('name', 191);
            $table->unsignedBigInteger('size');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('wa_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->foreignUuid('device_id')->constrained('wa_devices')->cascadeOnDelete();
            $table->string('name', 150);
            // draft | running | paused | done | cancelled
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['device_id', 'status']);
        });

        // Antrean kirim. Manual (priority 0) selalu didahulukan dari blast (priority 10).
        Schema::create('wa_outbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->foreignUuid('device_id')->constrained('wa_devices')->cascadeOnDelete();
            $table->foreignUuid('campaign_id')->nullable()->constrained('wa_campaigns')->cascadeOnDelete();
            // 628xxx atau JID lengkap (grup)
            $table->string('chat_jid', 100);
            $table->string('type', 20)->default('text');
            $table->text('body')->nullable();
            $table->foreignUuid('upload_id')->nullable()->constrained('wa_uploads')->nullOnDelete();
            $table->string('quoted_wa_id', 128)->nullable();
            $table->unsignedTinyInteger('priority')->default(0);
            // queued | sending | sent | failed | skipped | cancelled
            $table->string('status', 20)->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('available_at')->useCurrent();
            $table->uuid('message_id')->nullable();
            $table->string('error')->nullable();
            $table->string('client_ref', 64)->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'client_ref']);
            $table->index(['device_id', 'status', 'priority', 'available_at']);
            $table->index(['campaign_id', 'status']);
        });

        Schema::create('wa_optouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->string('phone', 20);
            $table->timestamp('created_at')->nullable();

            $table->unique(['branch_id', 'phone']);
        });

        Schema::create('wa_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('branch_id')->index();
            $table->string('event', 50);
            $table->json('payload');
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->useCurrent();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        foreach (['wa_webhook_events', 'wa_optouts', 'wa_outbox', 'wa_campaigns', 'wa_uploads', 'wa_messages', 'wa_chats', 'wa_devices'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};

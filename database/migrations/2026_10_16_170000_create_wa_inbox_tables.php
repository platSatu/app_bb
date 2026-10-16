<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| WhatsApp tahap 2: inbox (label, catatan, assignment, balasan cepat, polling),
| riwayat status device, API key per device, dan kelompok kontak (Phone Book > Group).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('media_size');
        });

        Schema::table('wa_outbox', function (Blueprint $table) {
            $table->json('payload')->nullable()->after('quoted_wa_id');
        });

        Schema::table('wa_chats', function (Blueprint $table) {
            $table->foreignUuid('assigned_user_id')->nullable()->after('is_group')->constrained('users')->nullOnDelete();
        });

        Schema::create('wa_poll_votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained('wa_messages')->cascadeOnDelete();
            $table->string('voter_jid', 100);
            $table->json('selected_options');
            $table->timestamp('voted_at')->nullable();
            $table->timestamps();

            $table->unique(['message_id', 'voter_jid']);
        });

        // Ditulis go_bizbos setiap status device berubah, bahan skor kesehatan.
        Schema::create('wa_device_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('device_id')->constrained('wa_devices')->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['device_id', 'created_at']);
        });

        Schema::create('wa_labels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->string('name', 50);
            $table->string('color', 7)->default('#16a34a');
            $table->timestamps();

            $table->unique(['branch_id', 'name']);
        });

        Schema::create('wa_chat_label', function (Blueprint $table) {
            $table->foreignUuid('chat_id')->constrained('wa_chats')->cascadeOnDelete();
            $table->foreignUuid('label_id')->constrained('wa_labels')->cascadeOnDelete();

            $table->primary(['chat_id', 'label_id']);
        });

        Schema::create('wa_chat_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('chat_id')->constrained('wa_chats')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note');
            $table->timestamps();
        });

        Schema::create('wa_quick_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->string('title', 100);
            $table->string('shortcut', 30)->nullable();
            $table->text('body');
            $table->timestamps();

            $table->unique(['branch_id', 'shortcut']);
        });

        // API untuk aplikasi lain mengirim WA lewat device ini. Secret terenkripsi, ditampilkan sekali.
        Schema::create('wa_api_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->foreignUuid('device_id')->unique()->constrained('wa_devices')->cascadeOnDelete();
            $table->string('api_key', 64)->unique();
            $table->text('secret');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('wa_api_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('api_key_id')->constrained('wa_api_keys')->cascadeOnDelete();
            $table->string('method', 10);
            $table->string('path', 100);
            $table->unsignedSmallInteger('status');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['api_key_id', 'created_at']);
        });

        // Phone Book > Group: kelompok kontak dari Buku Kontak, dipakai juga untuk blast.
        Schema::create('contact_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'name']);
        });

        Schema::create('contact_group_customer', function (Blueprint $table) {
            $table->foreignUuid('contact_group_id')->constrained('contact_groups')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->primary(['contact_group_id', 'customer_id']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        foreach (['contact_group_customer', 'contact_groups', 'wa_api_logs', 'wa_api_keys', 'wa_quick_replies', 'wa_chat_notes', 'wa_chat_label', 'wa_labels', 'wa_device_events', 'wa_poll_votes'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('wa_chats', fn (Blueprint $table) => $table->dropConstrainedForeignId('assigned_user_id'));
        Schema::table('wa_outbox', fn (Blueprint $table) => $table->dropColumn('payload'));
        Schema::table('wa_messages', fn (Blueprint $table) => $table->dropColumn('meta'));
    }
};

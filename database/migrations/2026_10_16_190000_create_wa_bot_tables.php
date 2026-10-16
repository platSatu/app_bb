<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| WhatsApp tahap 4: Auto Reply, Chatbot Flow, AI Bot.
| Satu pesan masuk hanya dijawab satu pihak (urutan di App\Services\WhatsApp\Bot\BotRouter).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_chats', function (Blueprint $table) {
            // Bot diam sampai waktu ini (diambil alih admin / minta bicara dengan admin).
            $table->timestamp('bot_paused_until')->nullable()->after('assigned_user_id');
            // Balasan terakhir dari manusia (inbox bizbos atau langsung dari HP).
            $table->timestamp('human_replied_at')->nullable()->after('bot_paused_until');
        });

        Schema::create('wa_auto_replies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            // null = semua device di branch
            $table->foreignUuid('device_id')->nullable()->constrained('wa_devices')->cascadeOnDelete();
            $table->string('name', 100);
            // exact | starts_with | contains | any
            $table->string('match_type', 20)->default('contains');
            $table->json('keywords')->nullable();
            $table->text('body');
            $table->unsignedInteger('cooldown_minutes')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'is_active']);
        });

        Schema::create('wa_chatbot_flows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->foreignUuid('device_id')->nullable()->constrained('wa_devices')->cascadeOnDelete();
            $table->string('name', 100);
            // exact | contains
            $table->string('match_type', 20)->default('exact');
            $table->json('keywords');
            // {nodes: [{id, message, options: [{key, label, action: next|end|handover, next}]}]}
            $table->json('nodes');
            $table->string('end_message', 1000)->nullable();
            $table->string('handover_message', 1000)->nullable();
            $table->unsignedSmallInteger('timeout_minutes')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['branch_id', 'is_active']);
        });

        Schema::create('wa_bot_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('chat_id')->unique()->constrained('wa_chats')->cascadeOnDelete();
            $table->foreignUuid('flow_id')->constrained('wa_chatbot_flows')->cascadeOnDelete();
            $table->string('node', 40);
            $table->unsignedTinyInteger('invalid_count')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        // Satu AI bot per device. API key milik user sendiri, terenkripsi.
        Schema::create('wa_ai_bots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->foreignUuid('device_id')->unique()->constrained('wa_devices')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('driver', 20);
            $table->string('model', 100);
            $table->text('api_key');
            $table->text('system_prompt')->nullable();
            $table->longText('knowledge')->nullable();
            $table->unsignedSmallInteger('max_tokens')->default(400);
            $table->unsignedSmallInteger('handover_minutes')->default(120);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('wa_ai_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('bot_id')->constrained('wa_ai_bots')->cascadeOnDelete();
            $table->foreignUuid('chat_id')->nullable()->constrained('wa_chats')->nullOnDelete();
            $table->string('status', 20);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->string('error')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['bot_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['wa_ai_logs', 'wa_ai_bots', 'wa_bot_sessions', 'wa_chatbot_flows', 'wa_auto_replies'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('wa_chats', fn (Blueprint $table) => $table->dropColumn(['bot_paused_until', 'human_replied_at']));
    }
};

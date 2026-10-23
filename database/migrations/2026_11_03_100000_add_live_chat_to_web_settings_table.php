<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live chat bizbos.id (Superadmin > Settings > Live Chat): on/off, sapaan,
 * pertanyaan cepat (satu per baris), dan pengetahuan tambahan untuk asisten AI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $table->boolean('chat_enabled')->default(true)->after('running_text');
            $table->string('chat_greeting', 300)->nullable()->after('chat_enabled');
            $table->text('chat_questions')->nullable()->after('chat_greeting');
            $table->text('chat_knowledge')->nullable()->after('chat_questions');
        });
    }

    public function down(): void
    {
        Schema::table('web_settings', function (Blueprint $table) {
            $table->dropColumn(['chat_enabled', 'chat_greeting', 'chat_questions', 'chat_knowledge']);
        });
    }
};

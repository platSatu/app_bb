<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent Prospek tahap 2: draf email pembuka per prospek -> disetujui superadmin -> dikirim lewat Email Blast.
 * outreach_status: draft | approved | sent | skipped (ditolak / email dobel). NULL = belum pernah disiapkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->string('outreach_status', 20)->nullable()->after('contacted_at')->index();
            $table->string('outreach_subject', 200)->nullable()->after('outreach_status');
            $table->text('outreach_body')->nullable()->after('outreach_subject');
            $table->foreignUuid('email_blast_id')->nullable()->after('outreach_body')->constrained('email_blasts')->nullOnDelete();
        });

        Schema::table('prospect_agent_settings', function (Blueprint $table) {
            $table->json('templates')->nullable()->after('cities');                    // {"Nama segmen": {subject, body}}
            $table->unsignedTinyInteger('min_score')->default(60)->after('templates'); // skor minimal untuk dibuatkan draf
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('email_blast_id');
            $table->dropIndex(['outreach_status']);
            $table->dropColumn(['outreach_status', 'outreach_subject', 'outreach_body']);
        });

        Schema::table('prospect_agent_settings', function (Blueprint $table) {
            $table->dropColumn(['templates', 'min_score']);
        });
    }
};

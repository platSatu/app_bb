<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agent Prospek (Tools › Prospek › Agent): setiap pagi mencari calon pelanggan lewat Open Places menurut rotasi
 * segmen × kota, menilai kecocokan (aturan sederhana, atau AI kalau provider AI aktif), lalu menyimpan ke Prospek.
 * Belum mengirim pesan apa pun. Riwayat 50 putaran terakhir disimpan di kolom runs (tanpa tabel log terpisah).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_agent_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->boolean('is_active')->default(false);
            $table->json('segments')->nullable();                 // [{name, keywords: [...]}]
            $table->json('cities')->nullable();                   // ["Bekasi", ...]
            $table->unsignedSmallInteger('daily_quota')->default(30);
            $table->string('run_at', 5)->default('06:00');        // jam WIB
            $table->string('report_email', 150)->nullable();
            $table->unsignedInteger('cursor')->default(0);        // giliran rotasi berikutnya
            $table->date('last_run_on')->nullable();
            $table->json('runs')->nullable();                     // riwayat putaran terakhir
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('prospects', function (Blueprint $table) {
            $table->string('segment', 60)->nullable()->after('category')->index();
            $table->unsignedTinyInteger('score')->nullable()->after('segment')->index();
            $table->string('score_reason', 255)->nullable()->after('score');
        });
    }

    public function down(): void
    {
        Schema::table('prospects', function (Blueprint $table) {
            $table->dropIndex(['segment']);
            $table->dropIndex(['score']);
            $table->dropColumn(['segment', 'score', 'score_reason']);
        });

        Schema::dropIfExists('prospect_agent_settings');
    }
};

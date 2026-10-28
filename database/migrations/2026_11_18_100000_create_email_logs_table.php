<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat semua email aplikasi (diisi otomatis oleh App\Support\EmailLogRecorder).
 * Hanya tujuan, judul & status yang disimpan; isi email (mis. link reset password) tidak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 150);
            $table->string('recipient', 500);
            $table->string('subject')->nullable();
            // queued | sent | failed
            $table->string('status', 20)->default('queued');
            $table->string('error', 500)->nullable();
            // uuid job antrean: untuk menyambungkan status & "Kirim Ulang" (queue:retry).
            $table->uuid('job_uuid')->nullable()->index();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};

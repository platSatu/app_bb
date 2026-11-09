<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tools › Email Blast (superadmin): kirim email penawaran dari akun email hosting tersendiri (bukan MAIL_* di .env),
 * plus Inbox (dibaca langsung lewat IMAP, tidak disimpan) & riwayat Terkirim.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Satu baris pengaturan akun email pengirim. Password terenkripsi (cast di model).
        Schema::create('email_blast_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('from_name', 100)->nullable();
            $table->string('from_address', 150)->nullable();
            $table->string('host', 150)->nullable();              // mis. mail.bizbos.id (SMTP & IMAP sama)
            $table->string('username', 150)->nullable();
            $table->text('password')->nullable();
            $table->string('encryption', 5)->default('ssl');      // ssl | tls
            $table->unsignedSmallInteger('smtp_port')->default(465);
            $table->unsignedSmallInteger('imap_port')->default(993);
            $table->unsignedInteger('daily_limit')->default(100);
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Satu baris per blast. Hasil per penerima di kolom results ({"email": "sent" | "pesan gagal"}).
        Schema::create('email_blasts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('subject', 200);
            $table->text('body');
            $table->json('recipients');
            $table->json('results')->nullable();
            $table->string('status', 20)->default('queued')->index(); // queued | sending | done
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_blasts');
        Schema::dropIfExists('email_blast_settings');
    }
};

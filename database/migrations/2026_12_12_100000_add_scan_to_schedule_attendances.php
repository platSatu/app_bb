<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Absen scan QR:
 *  - pengajar scan QR tempat les -> jam absen pengajar per sesi (schedule_sessions.teacher_checked_in_at);
 *  - murid discan pengajar -> jam scan & akun pemindai (schedule_attendances), sekali per sesi;
 *  - versi QR tempat per branch, naik saat owner menekan Ganti QR supaya QR lama (mis. yang difoto) tidak berlaku.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_attendances', function (Blueprint $table) {
            $table->timestamp('scanned_at')->nullable()->after('notes');
            $table->foreignUuid('scanned_by')->nullable()->after('scanned_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('schedule_sessions', function (Blueprint $table) {
            $table->timestamp('teacher_checked_in_at')->nullable()->after('end_at');
        });

        Schema::table('user_branches', function (Blueprint $table) {
            $table->unsignedInteger('place_qr_version')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('schedule_attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scanned_by');
            $table->dropColumn('scanned_at');
        });

        Schema::table('schedule_sessions', fn (Blueprint $table) => $table->dropColumn('teacher_checked_in_at'));
        Schema::table('user_branches', fn (Blueprint $table) => $table->dropColumn('place_qr_version'));
    }
};

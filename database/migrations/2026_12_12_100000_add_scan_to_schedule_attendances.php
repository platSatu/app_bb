<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Absen scan QR:
 *  - pengajar scan QR tempat les -> jam absen pengajar per sesi (schedule_sessions.teacher_checked_in_at);
 *  - murid discan pengajar -> jam scan & akun pemindai (schedule_attendances), sekali per sesi;
 *  - versi QR tempat per branch, naik saat owner menekan Ganti QR supaya QR lama (mis. yang difoto) tidak berlaku.
 * Setiap kolom dicek dulu, karena versi awal migration ini (hanya kolom scan murid) sempat dijalankan di beberapa server.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('schedule_attendances', 'scanned_at')) {
            Schema::table('schedule_attendances', function (Blueprint $table) {
                $table->timestamp('scanned_at')->nullable()->after('notes');
                $table->foreignUuid('scanned_by')->nullable()->after('scanned_at')->constrained('users')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('schedule_sessions', 'teacher_checked_in_at')) {
            Schema::table('schedule_sessions', fn (Blueprint $table) => $table->timestamp('teacher_checked_in_at')->nullable()->after('end_at'));
        }

        if (! Schema::hasColumn('user_branches', 'place_qr_version')) {
            Schema::table('user_branches', fn (Blueprint $table) => $table->unsignedInteger('place_qr_version')->default(1));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('schedule_attendances', 'scanned_by')) {
            Schema::table('schedule_attendances', fn (Blueprint $table) => $table->dropConstrainedForeignId('scanned_by'));
        }

        foreach (['schedule_attendances' => 'scanned_at', 'schedule_sessions' => 'teacher_checked_in_at', 'user_branches' => 'place_qr_version'] as $table => $column) {
            if (Schema::hasColumn($table, $column)) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};

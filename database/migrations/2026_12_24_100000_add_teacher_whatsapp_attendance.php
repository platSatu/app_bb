<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chatbot WhatsApp Jadwal tahap 2 (pengajar):
 *  - schedule_attendances.marked_*: absen murid yang diisi pengajar lewat WhatsApp (pengganti scan kartu).
 *  - schedule_sessions.teacher_checkin_*: absen datang pengajar lewat apa (qr | whatsapp untuk kelas online) & jaraknya dari lokasi.
 *  - schedule_sessions.teacher_unavailable_at: pengajar mengabarkan berhalangan lewat WhatsApp (admin yang mengatur).
 *  - schedule_rooms.is_online: ruang kelas online (tanpa QR tempat / GPS).
 *  - user_branches: titik lokasi tempat les (cek GPS saat scan QR tempat) & ganti QR otomatis tiap awal bulan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_attendances', function (Blueprint $table) {
            $table->string('marked_via', 20)->nullable()->after('scanned_by');
            $table->timestamp('marked_at')->nullable()->after('marked_via');
        });

        Schema::table('schedule_sessions', function (Blueprint $table) {
            $table->string('teacher_checkin_via', 20)->nullable()->after('teacher_checked_in_at');
            $table->unsignedInteger('teacher_checkin_distance')->nullable()->after('teacher_checkin_via');
            $table->timestamp('teacher_unavailable_at')->nullable()->after('teacher_checkin_distance');
        });

        Schema::table('schedule_rooms', function (Blueprint $table) {
            $table->boolean('is_online')->default(false)->after('name');
        });

        Schema::table('user_branches', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('place_qr_auto_rotate')->default(false);
            // Setelah ganti QR otomatis, QR versi sebelumnya masih diterima sampai waktu ini (sempat cetak ulang).
            $table->timestamp('place_qr_grace_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('user_branches', fn (Blueprint $table) => $table->dropColumn(['latitude', 'longitude', 'place_qr_auto_rotate', 'place_qr_grace_until']));
        Schema::table('schedule_rooms', fn (Blueprint $table) => $table->dropColumn('is_online'));
        Schema::table('schedule_sessions', fn (Blueprint $table) => $table->dropColumn(['teacher_checkin_via', 'teacher_checkin_distance', 'teacher_unavailable_at']));
        Schema::table('schedule_attendances', fn (Blueprint $table) => $table->dropColumn(['marked_via', 'marked_at']));
    }
};

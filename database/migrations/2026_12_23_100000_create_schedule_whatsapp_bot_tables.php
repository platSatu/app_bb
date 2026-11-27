<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chatbot WhatsApp Jadwal (tahap 1: murid / orang tua + persetujuan admin).
 *  - schedule_attendances.response*: jawaban murid / orang tua atas pengingat (konfirmasi / izin), dicatat dari mana & kapan.
 *  - schedule_reschedule_requests: pengajuan ganti hari, menunggu persetujuan admin (lewat WhatsApp atau website).
 *  - wa_schedule_conversations: posisi satu nomor di percakapan bot Jadwal (satu baris per nomor per branch).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_attendances', function (Blueprint $table) {
            // confirmed | excused. Kosong = belum menjawab.
            $table->string('response', 20)->nullable()->after('notes');
            $table->timestamp('responded_at')->nullable()->after('response');
            // whatsapp | web
            $table->string('responded_via', 20)->nullable()->after('responded_at');
        });

        Schema::create('schedule_reschedule_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->foreignUuid('session_id')->constrained('schedule_sessions')->cascadeOnDelete();
            $table->foreignUuid('attendance_id')->constrained('schedule_attendances')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('schedule_students')->cascadeOnDelete();
            $table->string('reason', 255)->nullable();
            // Jadwal yang diminta.
            $table->dateTime('slot_start_at');
            $table->dateTime('slot_end_at');
            $table->foreignUuid('slot_teacher_id')->constrained('schedule_teachers')->cascadeOnDelete();
            $table->foreignUuid('slot_room_id')->constrained('schedule_rooms')->cascadeOnDelete();
            // move = kelas privat, sesinya dipindah. makeup = kelas grup, sesi pengganti dibuat setelah sesi asli ditutup.
            $table->string('mode', 20);
            $table->string('status', 20)->default('pending');
            $table->string('reject_reason', 255)->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->foreignUuid('makeup_session_id')->nullable()->constrained('schedule_sessions')->nullOnDelete();
            $table->string('requested_via', 20)->default('whatsapp');
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reviewed_via', 20)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'created_at']);
            $table->index(['session_id', 'status']);
        });

        Schema::create('wa_schedule_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->string('phone', 20);
            // student | admin
            $table->string('role', 20);
            $table->string('step', 30);
            $table->json('context')->nullable();
            $table->unsignedTinyInteger('invalid_count')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['branch_id', 'phone']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_schedule_conversations');
        Schema::dropIfExists('schedule_reschedule_requests');

        Schema::table('schedule_attendances', fn (Blueprint $table) => $table->dropColumn(['response', 'responded_at', 'responded_via']));
    }
};

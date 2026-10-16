<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Schedules tahap 2: jadwal rutin mingguan -> sesi per tanggal -> absensi per murid.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_routines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->foreignUuid('grade_id')->constrained('schedule_grades')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained('schedule_teachers')->cascadeOnDelete();
            $table->foreignUuid('room_id')->constrained('schedule_rooms')->cascadeOnDelete();
            // 1 = Senin ... 7 = Minggu.
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('duration_minutes');
            // 1 = privat, lebih dari 1 = kelas grup.
            $table->unsignedTinyInteger('capacity')->default(1);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['branch_id', 'day_of_week', 'status'], 'schedule_routines_day_index');
        });

        Schema::create('schedule_participants', function (Blueprint $table) {
            $table->foreignUuid('routine_id')->constrained('schedule_routines')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('schedule_students')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['routine_id', 'student_id']);
        });

        Schema::create('schedule_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            // Kosong = sesi pengganti (dibuat manual untuk murid yang izin).
            $table->foreignUuid('routine_id')->nullable()->constrained('schedule_routines')->cascadeOnDelete();
            $table->foreignUuid('grade_id')->constrained('schedule_grades')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->constrained('schedule_teachers')->cascadeOnDelete();
            $table->foreignUuid('room_id')->constrained('schedule_rooms')->cascadeOnDelete();
            // Bulan jatah pertemuan (Y-m), tetap walau sesinya dipindah tanggal. Batas: pertemuan per bulan di grade.
            $table->char('period', 7)->nullable();
            $table->date('date');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->string('status', 20)->default('scheduled');
            $table->dateTime('moved_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->foreignUuid('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['routine_id', 'date']);
            $table->index(['routine_id', 'period']);
            $table->index(['branch_id', 'start_at']);
            $table->index(['teacher_id', 'start_at']);
            $table->index(['room_id', 'start_at']);
        });

        Schema::create('schedule_attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('session_id')->constrained('schedule_sessions')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('schedule_students')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            // Dicatat saat sesi diselesaikan (hadir/alpa), supaya laporan lama tidak berubah kalau harga grade diubah.
            $table->unsignedInteger('teacher_fee')->default(0);
            $table->unsignedInteger('owner_fee')->default(0);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'student_id']);
        });

        Schema::table('schedule_attendances', function (Blueprint $table) {
            // Murid izin -> dibuatkan sesi pengganti; sesi pengganti tahu absensi mana yang digantikan.
            $table->foreignUuid('makeup_session_id')->nullable()->after('notes')->constrained('schedule_sessions')->nullOnDelete();
            $table->foreignUuid('replaces_attendance_id')->nullable()->after('makeup_session_id')->constrained('schedule_attendances')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_attendances');
        Schema::dropIfExists('schedule_sessions');
        Schema::dropIfExists('schedule_participants');
        Schema::dropIfExists('schedule_routines');
    }
};

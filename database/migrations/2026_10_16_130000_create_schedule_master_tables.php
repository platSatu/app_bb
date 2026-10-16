<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Aplikasi Jadwal tahap 1: Ruangan > Bidang > Kategori > Grade, plus Pengajar & Murid. Semua per branch.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['branch_id', 'name']);
        });

        Schema::create('schedule_subjects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->foreignUuid('room_id')->constrained('schedule_rooms')->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['room_id', 'name']);
        });

        Schema::create('schedule_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('schedule_subjects')->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['subject_id', 'name']);
        });

        Schema::create('schedule_grades', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->foreignUuid('category_id')->constrained('schedule_categories')->cascadeOnDelete();
            $table->string('name', 150);
            $table->text('description')->nullable();
            // Harga paket per bulan untuk sejumlah pertemuan; bagian pengajar dalam persen, sisanya owner.
            $table->unsignedBigInteger('price')->default(0);
            $table->unsignedTinyInteger('meetings_per_month')->default(4);
            $table->unsignedSmallInteger('duration_minutes')->default(60);
            $table->decimal('teacher_percent', 5, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['category_id', 'name']);
        });

        Schema::create('schedule_teachers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            // Login dashboard pengajar (diaktifkan di tahap berikutnya).
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->dateTime('last_login_at')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['branch_id', 'email']);
            $table->unique(['branch_id', 'phone']);
        });

        Schema::create('schedule_teacher_grades', function (Blueprint $table) {
            $table->foreignUuid('teacher_id')->constrained('schedule_teachers')->cascadeOnDelete();
            $table->foreignUuid('grade_id')->constrained('schedule_grades')->cascadeOnDelete();

            $table->primary(['teacher_id', 'grade_id']);
        });

        Schema::create('schedule_students', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('user_branches')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('name', 150);
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('guardian_name', 150)->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->dateTime('last_login_at')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['branch_id', 'email']);
            $table->unique(['branch_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_students');
        Schema::dropIfExists('schedule_teacher_grades');
        Schema::dropIfExists('schedule_teachers');
        Schema::dropIfExists('schedule_grades');
        Schema::dropIfExists('schedule_categories');
        Schema::dropIfExists('schedule_subjects');
        Schema::dropIfExists('schedule_rooms');
    }
};

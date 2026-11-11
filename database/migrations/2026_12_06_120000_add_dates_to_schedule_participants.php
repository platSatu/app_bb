<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arsip kelas yang jelas: peserta jadwal punya tanggal masuk & keluar (bukan dihapus), murid punya tanggal resign.
 * Peserta lama: joined_on kosong = ikut sejak jadwal mulai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_participants', function (Blueprint $table) {
            $table->date('joined_on')->nullable()->after('student_id');
            $table->date('left_on')->nullable()->after('joined_on');
        });

        Schema::table('schedule_students', function (Blueprint $table) {
            $table->date('left_on')->nullable()->after('status'); // tanggal resign / keluar
        });
    }

    public function down(): void
    {
        Schema::table('schedule_students', function (Blueprint $table) {
            $table->dropColumn('left_on');
        });

        Schema::table('schedule_participants', function (Blueprint $table) {
            $table->dropColumn(['joined_on', 'left_on']);
        });
    }
};

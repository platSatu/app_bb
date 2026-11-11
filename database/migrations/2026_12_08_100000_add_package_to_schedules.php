<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas paket (privat): grade "paket" = harga total untuk sejumlah pertemuan (meetings_per_month dipakai sebagai
 * jumlah pertemuan paket). Jadwalnya menyimpan pola pertemuan:
 *   {"type":"weekly","days":[2,4]} | {"type":"interval","every":3} | {"type":"manual","dates":["2026-12-01", ...]}
 * Pola kosong = jadwal bulanan biasa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_grades', function (Blueprint $table) {
            $table->string('billing', 10)->default('monthly')->after('description');
        });

        Schema::table('schedule_routines', function (Blueprint $table) {
            $table->json('pattern')->nullable()->after('day_of_week');
        });
    }

    public function down(): void
    {
        Schema::table('schedule_routines', function (Blueprint $table) {
            $table->dropColumn('pattern');
        });

        Schema::table('schedule_grades', function (Blueprint $table) {
            $table->dropColumn('billing');
        });
    }
};

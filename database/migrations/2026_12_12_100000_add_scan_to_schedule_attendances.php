<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Jejak absen scan QR: jam scan & akun pengajar yang memindai. Satu murid hanya bisa discan sekali per sesi. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedule_attendances', function (Blueprint $table) {
            $table->timestamp('scanned_at')->nullable()->after('notes');
            $table->foreignUuid('scanned_by')->nullable()->after('scanned_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('schedule_attendances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scanned_by');
            $table->dropColumn('scanned_at');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat aktivitas pengguna app.bizbos (menu yang dibuka & aksi yang dilakukan), ditulis oleh
 * App\Http\Middleware\LogUserActivity. Isi form TIDAK disimpan. Dihapus otomatis setelah
 * UserActivityLog::KEEP_DAYS hari (model:prune). Id angka biasa supaya insert ringan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('route_name', 150)->nullable();
            $table->string('method', 10);
            $table->string('path', 255);
            $table->unsignedSmallInteger('status');
            $table->string('ip_address', 45)->nullable();
            $table->string('browser', 50)->nullable();
            $table->string('os', 50)->nullable();
            $table->string('device_type', 20)->nullable();
            $table->timestamp('created_at')->index();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_logs');
    }
};

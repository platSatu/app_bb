<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Asisten AI: selain teks, pesan bisa berisi gambar, video atau suara dari Classai Router.
        Schema::table('ai_messages', function (Blueprint $table) {
            // text | image | video | audio
            $table->string('kind', 10)->default('text')->after('role');
            // File hasil (disimpan di storage/app/private, dibuka lewat route yang dicek pemiliknya): [{path, mime, size}]
            $table->json('media')->nullable()->after('content');
            // Pilihan saat membuat (ukuran, resolusi, durasi, suara, gaya, biaya, dll.).
            $table->json('options')->nullable()->after('media');
            // ID tugas video di Classai (dicek berkala sampai selesai).
            $table->string('job_id', 100)->nullable()->after('options');
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->dropColumn(['kind', 'media', 'options', 'job_id']);
        });
    }
};

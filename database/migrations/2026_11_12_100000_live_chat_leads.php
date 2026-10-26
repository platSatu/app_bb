<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live chat bizbos.id: pengunjung mengisi nama, email, WhatsApp sebelum chat dan tersimpan sebagai
 * Customer aplikasi "website" milik platform (tanpa company/branch; hanya terlihat superadmin).
 * web_settings.chat_ai: jawaban memakai AI hanya kalau dinyalakan superadmin (default: tanpa AI, dari FAQ).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->uuid('company_id')->nullable()->change();
            $table->uuid('branch_id')->nullable()->change();
        });

        Schema::table('web_settings', function (Blueprint $table) {
            $table->boolean('chat_ai')->default(false)->after('chat_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('web_settings', fn (Blueprint $table) => $table->dropColumn('chat_ai'));
        // company_id/branch_id sengaja tidak dikembalikan ke NOT NULL: baris customer "website" bisa sudah ada.
    }
};

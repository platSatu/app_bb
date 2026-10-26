<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Kapan & versi Syarat & Ketentuan mana yang disetujui user saat mendaftar (form daftar / Google).
// NULL = akun lama atau dibuat dari dalam (superadmin, akun customer), bukan pendaftaran mandiri.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable()->after('email_verified_at');
            $table->foreignUuid('terms_id')->nullable()->after('terms_accepted_at')->constrained('web_term_conditions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('terms_id');
            $table->dropColumn('terms_accepted_at');
        });
    }
};

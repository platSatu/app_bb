<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Link petugas registrasi ulang: dibuka lewat URL ber-token + PIN, tanpa akun.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_scanners', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained('page_categories')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('token', 64)->unique();
            $table->string('pin_hash');
            $table->string('status', 20)->default('active');
            $table->dateTime('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignUuid('checked_in_scanner_id')->nullable()->after('checked_in_by')->constrained('ticket_scanners')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('checked_in_scanner_id');
        });

        Schema::dropIfExists('ticket_scanners');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ID card & absensi per sesi untuk page Tiket.
 * - E-ticket (tickets.token) ditukar jadi ID card dengan QR SENDIRI (tickets.card_token),
 *   jadi QR e-ticket dan QR ID card tidak pernah tertukar.
 * - Link petugas (ticket_scanners) sekarang punya jenis: registrasi ulang, kiosk cetak mandiri, absensi kelas.
 * - Absensi: 1 tiket hanya bisa 1x per sesi, dijaga unique index (aman walau discan bersamaan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('card_token', 64)->nullable()->unique()->after('token');
            $table->dateTime('card_printed_at')->nullable()->after('card_token');
            $table->unsignedSmallInteger('card_print_count')->default(0)->after('card_printed_at');
        });

        Schema::table('ticket_scanners', function (Blueprint $table) {
            $table->string('type', 20)->default('checkin')->after('page_id');
            $table->index(['page_id', 'type']);
        });

        Schema::create('ticket_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained('page_categories')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('room', 100)->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            // Scan dibuka X menit sebelum mulai; ditutup Y menit setelah mulai (null = sampai sesi selesai).
            $table->unsignedSmallInteger('open_before_minutes')->default(30);
            $table->unsignedSmallInteger('late_minutes')->nullable();
            // null = semua kategori tiket boleh ikut.
            $table->json('category_ids')->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['page_id', 'starts_at']);
        });

        Schema::create('ticket_session_attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained('page_categories')->cascadeOnDelete();
            $table->foreignUuid('session_id')->constrained('ticket_sessions')->cascadeOnDelete();
            $table->foreignUuid('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignUuid('scanner_id')->nullable()->constrained('ticket_scanners')->nullOnDelete();
            $table->dateTime('scanned_at');
            $table->timestamps();

            $table->unique(['session_id', 'ticket_id'], 'ticket_attendance_unique');
            $table->index(['session_id', 'scanned_at']);
        });

        // Jejak cetak ID card: hanya ditambah, tidak pernah diubah.
        Schema::create('ticket_card_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->constrained('page_categories')->cascadeOnDelete();
            $table->foreignUuid('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('action', 20);
            $table->string('source', 20);
            $table->foreignUuid('scanner_id')->nullable()->constrained('ticket_scanners')->nullOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['page_id', 'created_at']);
            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_card_logs');
        Schema::dropIfExists('ticket_session_attendances');
        Schema::dropIfExists('ticket_sessions');

        Schema::table('ticket_scanners', function (Blueprint $table) {
            $table->dropIndex(['page_id', 'type']);
            $table->dropColumn('type');
        });

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropUnique(['card_token']);
            $table->dropColumn(['card_token', 'card_printed_at', 'card_print_count']);
        });
    }
};

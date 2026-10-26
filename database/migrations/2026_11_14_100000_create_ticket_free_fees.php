<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

/**
 * Biaya tiket GRATIS: dibayar di depan dari Saldo per tiket, saat kategori disimpan (lihat FreeTicketFeeService).
 * ticket_settings: jatah gratis per event & tarif per tiket (diatur superadmin).
 * ticket_free_fees: 1 baris per event, mencatat tarif & jatah yang dikunci, dan berapa tiket yang sudah dibayar.
 * Event tiket yang sudah ada sebelum fitur ini ditandai bebas biaya (exempt), supaya tetap berjalan seperti biasa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_settings', function (Blueprint $table) {
            $table->unsignedInteger('free_allowance')->default(50)->after('fee_percent');
            $table->unsignedInteger('free_unit_price')->default(500)->after('free_allowance');
        });

        Schema::create('ticket_free_fees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('page_id')->unique()->constrained('page_categories')->cascadeOnDelete();
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('free_allowance');
            $table->unsignedInteger('paid_units')->default(0);
            // Naik setiap kali saldo dipotong/dikembalikan; bagian dari idempotency key mutasi saldo.
            $table->unsignedInteger('version')->default(0);
            $table->boolean('exempt')->default(false);
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('page_categories')
            ->join('page_types', 'page_types.id', '=', 'page_categories.page_type_id')
            ->where('page_types.template', 'ticket')
            ->pluck('page_categories.id')
            ->each(fn ($pageId) => DB::table('ticket_free_fees')->insert([
                'id' => (string) Str::uuid(), 'page_id' => $pageId, 'unit_price' => 0, 'free_allowance' => 0,
                'exempt' => true, 'created_at' => $now, 'updated_at' => $now,
            ]));
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_free_fees');
        Schema::table('ticket_settings', fn (Blueprint $table) => $table->dropColumn(['free_allowance', 'free_unit_price']));
    }
};

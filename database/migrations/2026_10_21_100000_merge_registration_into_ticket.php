<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipe page Registrasi dihapus; form builder sekarang jadi "Form Pembeli" di page Tiket.
 * Jawaban pembeli tetap memakai page_submissions (+ file privat), ditautkan ke pesanannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_orders', function (Blueprint $table) {
            $table->foreignUuid('submission_id')->nullable()->after('customer_id')->constrained('page_submissions')->nullOnDelete();
        });

        Schema::table('page_form_fields', function (Blueprint $table) {
            $table->dropColumn('contact_field');
        });

        // Hanya tipe yang belum dipakai page mana pun.
        DB::table('page_types')->where('template', 'registration')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('page_categories')->whereColumn('page_categories.page_type_id', 'page_types.id'))
            ->delete();
    }

    public function down(): void
    {
        Schema::table('page_form_fields', function (Blueprint $table) {
            $table->string('contact_field', 10)->nullable()->after('is_required');
        });

        Schema::table('ticket_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submission_id');
        });
    }
};

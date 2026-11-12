<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Siap war tiket & event besar:
 *  - tickets (page_id, updated_at): perangkat petugas scan berkala menanyakan "tiket yang berubah sejak ..." per event;
 *  - payment_callbacks (created_at): Antrean & Tugas menghitung callback bermasalah 24 jam terakhir tanpa membaca seluruh riwayat.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('tickets', 'tickets_page_updated_index')) {
            Schema::table('tickets', fn (Blueprint $table) => $table->index(['page_id', 'updated_at'], 'tickets_page_updated_index'));
        }

        if (! Schema::hasIndex('payment_callbacks', 'payment_callbacks_created_index')) {
            Schema::table('payment_callbacks', fn (Blueprint $table) => $table->index('created_at', 'payment_callbacks_created_index'));
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('tickets', 'tickets_page_updated_index')) {
            Schema::table('tickets', fn (Blueprint $table) => $table->dropIndex('tickets_page_updated_index'));
        }

        if (Schema::hasIndex('payment_callbacks', 'payment_callbacks_created_index')) {
            Schema::table('payment_callbacks', fn (Blueprint $table) => $table->dropIndex('payment_callbacks_created_index'));
        }
    }
};

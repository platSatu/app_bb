<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Mode lapangan scan tiket: perangkat petugas berkala menanyakan "tiket yang berubah sejak ..." per event. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('tickets', 'tickets_page_updated_index')) {
            Schema::table('tickets', fn (Blueprint $table) => $table->index(['page_id', 'updated_at'], 'tickets_page_updated_index'));
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('tickets', 'tickets_page_updated_index')) {
            Schema::table('tickets', fn (Blueprint $table) => $table->dropIndex('tickets_page_updated_index'));
        }
    }
};

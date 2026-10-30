<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Integrasi Web > Request Terakhir: tiap request kirim dicatat dengan ID antrean (wa_outbox) supaya tujuan,
| isi pesan, dan status kirimnya bisa ditampilkan (isi pesan tidak disalin ke log, cukup dirujuk), serta alasan
| singkat bila request ditolak.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_api_logs', function (Blueprint $table) {
            $table->uuid('outbox_id')->nullable()->after('status');
            $table->string('error', 255)->nullable()->after('outbox_id');
        });
    }

    public function down(): void
    {
        Schema::table('wa_api_logs', fn (Blueprint $table) => $table->dropColumn(['outbox_id', 'error']));
    }
};

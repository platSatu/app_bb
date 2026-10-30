<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Message WhatsApp jadi 3 jenis (seperti Pesan Terjadwal di Konexa):
|   once      = Sekali Kirim (sekarang / pada waktu tertentu)
|   recurring = Berulang (harian / mingguan / bulanan, dari tanggal mulai sampai tanggal berakhir)
|   drip      = Bertahap per Kontak (beberapa pesan, tiap pesan H+N hari dari tanggal mulai)
| Jenis lama "blast" (ke Group kontak) dilebur ke Sekali Kirim: tujuannya tetap tersimpan di target.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_broadcasts', function (Blueprint $table) {
            $table->foreignUuid('wa_template_id')->nullable()->after('upload_name')->constrained('wa_templates')->nullOnDelete();
            // Berulang & bertahap: patokan jadwal (tanggal mulai + jam kirim) dan batas akhir berulang.
            $table->timestamp('starts_at')->nullable()->after('schedule');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
        });

        Schema::create('wa_broadcast_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('broadcast_id')->constrained('wa_broadcasts')->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            // Dikirim pada tanggal mulai + delay_days, di jam kirim yang sama.
            $table->unsignedSmallInteger('delay_days')->default(0);
            $table->text('body')->nullable();
            $table->foreignUuid('upload_id')->nullable()->constrained('wa_uploads')->nullOnDelete();
            $table->string('upload_kind', 20)->nullable();
            $table->string('upload_name', 191)->nullable();
            $table->foreignUuid('wa_template_id')->nullable()->constrained('wa_templates')->nullOnDelete();
            $table->timestamps();

            $table->unique(['broadcast_id', 'position']);
        });

        DB::table('wa_broadcasts')->where('type', 'blast')->update(['type' => 'once']);
        DB::table('wa_broadcasts')->whereIn('schedule', ['daily', 'weekly', 'monthly'])->update(['type' => 'recurring']);
        DB::table('wa_broadcasts')->whereNull('starts_at')->update(['starts_at' => DB::raw('COALESCE(next_run_at, created_at)')]);
    }

    public function down(): void
    {
        DB::table('wa_broadcasts')->whereIn('type', ['recurring', 'drip'])->update(['type' => 'once']);
        Schema::dropIfExists('wa_broadcast_steps');
        Schema::table('wa_broadcasts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wa_template_id');
            $table->dropColumn(['starts_at', 'ends_at']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * API WhatsApp memakai format teleios (X-WA-Token + X-WA-Secret), jadi secret cukup dicocokkan:
 * disimpan sebagai hash SHA-256, bukan lagi terenkripsi. Bocor database tidak membocorkan secret.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wa_api_keys', function (Blueprint $table) {
            $table->char('secret_hash', 64)->nullable()->after('api_key');
        });

        DB::table('wa_api_keys')->orderBy('id')->each(function (object $row) {
            try {
                $hash = hash('sha256', Crypt::decryptString($row->secret));
            } catch (Throwable) {
                $hash = null; // tidak bisa dibaca: owner cukup klik "Buat Secret Key Baru"
            }
            DB::table('wa_api_keys')->where('id', $row->id)->update(['secret_hash' => $hash]);
        });

        Schema::table('wa_api_keys', fn (Blueprint $table) => $table->dropColumn('secret'));
    }

    public function down(): void
    {
        // Secret asli tidak bisa dikembalikan dari hash; owner perlu membuat Secret Key baru.
        Schema::table('wa_api_keys', function (Blueprint $table) {
            $table->text('secret')->nullable()->after('api_key');
            $table->dropColumn('secret_hash');
        });
    }
};

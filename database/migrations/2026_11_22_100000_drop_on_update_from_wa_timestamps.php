<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MariaDB (explicit_defaults_for_timestamp = OFF) otomatis memberi kolom TIMESTAMP NOT NULL
 * pertama di tabel "DEFAULT current_timestamp() ON UPDATE current_timestamp()".
 *
 * Akibatnya:
 * - wa_messages.sent_at ikut berubah ke jam sekarang setiap baris di-UPDATE
 *   (status terkirim/dibaca, edit, media), jadi jam pesan di inbox bergeser.
 * - wa_bot_sessions.expires_at ikut berubah setiap sesi bot di-UPDATE.
 *
 * ON UPDATE dibuang; default saat insert tetap jam sekarang. Data tidak diubah.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'wa_messages' => 'sent_at',
        'wa_bot_sessions' => 'expires_at',
    ];

    public function up(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        foreach (self::COLUMNS as $table => $column) {
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
        }
    }

    public function down(): void
    {
        // Tidak dikembalikan: ON UPDATE pada kolom ini adalah bug, bukan perilaku yang diinginkan.
    }
};

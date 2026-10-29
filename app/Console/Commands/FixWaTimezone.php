<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Sekali jalan: merapikan jam data WhatsApp yang ditulis go_bizbos sebelum perbaikan zona waktu
 * (29 Okt 2026). Sesi MySQL go_bizbos dulu memakai UTC sementara nilainya WIB, sehingga kolom
 * TIMESTAMP tercatat 7 jam lebih maju.
 *
 * Baris "lama" = jamnya sebelum go_bizbos versi baru jalan (--cutoff) atau berada di masa depan.
 * Pengecualian: wa_messages.sent_at yang diset MariaDB sendiri lewat ON UPDATE current_timestamp()
 * (selisih tepat 7 jam dengan updated_at) sudah benar, jadi tidak digeser.
 *
 * Wajib menjalankan migration 2026_11_22_100000 lebih dulu (ON UPDATE dibuang), kalau tidak
 * UPDATE di sini ikut mengubah sent_at.
 */
class FixWaTimezone extends Command
{
    protected $signature = 'wa:fix-timezone
        {--cutoff=2026-10-29 17:56:48 : Jam (WIB) go_bizbos versi baru mulai jalan}
        {--dry-run : Hanya hitung baris yang akan diubah}';

    protected $description = 'Sekali jalan: geser mundur 7 jam data WhatsApp lama yang jamnya maju';

    private const MARKER = 'wa-timezone-fixed.txt';

    private const SHIFT = 'INTERVAL 7 HOUR';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $cutoff = (string) $this->option('cutoff');

        if (Storage::exists(self::MARKER)) {
            $this->error('Sudah pernah dijalankan ('.trim(Storage::get(self::MARKER)).'). Tidak dijalankan lagi supaya jam tidak tergeser dua kali.');

            return self::FAILURE;
        }
        if (! $this->onUpdateRemoved()) {
            $this->error('Kolom wa_messages.sent_at masih punya ON UPDATE. Jalankan "php artisan migrate --force" dulu.');

            return self::FAILURE;
        }

        $old = fn (string $col) => "({$col} < ? OR {$col} > NOW())";
        $steps = [
            // Urutan penting: sent_at memakai updated_at yang belum digeser.
            'wa_messages.sent_at' => [
                'wa_messages',
                "sent_at = sent_at - ".self::SHIFT,
                $old('updated_at').' AND NOT (TIMESTAMPDIFF(SECOND, sent_at, updated_at) BETWEEN 25190 AND 25210)',
                [$cutoff],
            ],
            'wa_messages.created_at' => ['wa_messages', 'created_at = created_at - '.self::SHIFT, $old('created_at'), [$cutoff]],
            'wa_messages.updated_at' => ['wa_messages', 'updated_at = updated_at - '.self::SHIFT, $old('updated_at'), [$cutoff]],
            'wa_chats.created_at' => ['wa_chats', 'created_at = created_at - '.self::SHIFT, $old('created_at'), [$cutoff]],
            'wa_chats.updated_at' => ['wa_chats', 'updated_at = updated_at - '.self::SHIFT, $old('updated_at'), [$cutoff]],
            'wa_devices.warmup_started_at' => ['wa_devices', 'warmup_started_at = warmup_started_at - '.self::SHIFT, $old('warmup_started_at'), [$cutoff]],
            'wa_devices.last_connected_at' => ['wa_devices', 'last_connected_at = last_connected_at - '.self::SHIFT, $old('last_connected_at'), [$cutoff]],
        ];

        $rows = [];
        DB::transaction(function () use ($steps, $dry, &$rows) {
            foreach ($steps as $label => [$table, $set, $where, $bindings]) {
                $count = DB::table($table)->whereRaw($where, $bindings)->count();
                if (! $dry && $count > 0) {
                    DB::update("UPDATE {$table} SET {$set} WHERE {$where}", $bindings);
                }
                $rows[] = [$label, $count];
            }

            // Urutan chat di inbox: jam pesan terakhir dihitung ulang dari pesan yang sudah dirapikan.
            $chats = DB::table('wa_chats')->whereExists(fn ($q) => $q->from('wa_messages')->whereColumn('wa_messages.chat_id', 'wa_chats.id'))->count();
            if (! $dry) {
                DB::update('UPDATE wa_chats c SET c.last_message_at = (SELECT MAX(m.sent_at) FROM wa_messages m WHERE m.chat_id = c.id)
                    WHERE EXISTS (SELECT 1 FROM wa_messages m2 WHERE m2.chat_id = c.id)');
            }
            $rows[] = ['wa_chats.last_message_at (hitung ulang)', $chats];
        });

        $this->table(['Kolom', $dry ? 'Akan diubah' : 'Diubah'], $rows);

        if ($dry) {
            $this->info('Dry run: belum ada data yang diubah.');
        } else {
            Storage::put(self::MARKER, now()->toDateTimeString().' cutoff '.$cutoff);
            $this->info('Selesai.');
        }

        return self::SUCCESS;
    }

    private function onUpdateRemoved(): bool
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return true;
        }
        $extra = DB::selectOne(
            'SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['wa_messages', 'sent_at'],
        )?->EXTRA ?? '';

        return ! str_contains(strtolower((string) $extra), 'on update');
    }
}

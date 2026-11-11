<?php

use Database\Seeders\PackageSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Susunan paket 11 Des 2026: hanya deskripsi yang diperbarui (teksnya dari PackageSeeder::description), harga tetap.
 *  - Pages & Event (Ticket) gratis dipakai sendiri; biaya tiket di kartu mengikuti Biaya Layanan Tiket ({biaya_tiket}).
 *  - Komplit menonjolkan Jadwal + Invoice + WhatsApp. Kata teknis (API) tidak dipakai di deskripsi paket.
 *  - "Pages + Invoice + WhatsApp" tidak dijual lagi: paket & tab-nya dinonaktifkan (voucher yang sudah dibeli tetap berlaku).
 * Hanya paket bernama baku buatan seeder yang disentuh; paket buatan manual di superadmin tidak diubah.
 */
return new class extends Migration
{
    private const LABELS = ['Trial 7 Hari', '6 Bulan', '12 Bulan', 'Gratis Selamanya'];

    public function up(): void
    {
        foreach (PackageSeeder::PLANS as $plan => $config) {
            $names = array_map(fn ($label) => "{$plan} - {$label}", self::LABELS);

            foreach (DB::table('packages')->whereIn('name', $names)->get(['id', 'apps', 'limits', 'is_trial']) as $package) {
                // Kuota di teks diambil dari batas paket yang berlaku sekarang (mungkin sudah diubah superadmin).
                $limits = collect(json_decode((string) $package->limits, true) ?: [])
                    ->map(fn ($limit) => $limit['monthly'] ?? null)->filter()->all();
                $notes = [...($package->is_trial ? ['Coba gratis 7 hari'] : []), ...($config['notes'] ?? [])];

                DB::table('packages')->where('id', $package->id)->update([
                    'description' => PackageSeeder::description(json_decode((string) $package->apps, true) ?: $config['apps'], $limits, $notes),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('packages')->where('name', 'like', 'Pages + Invoice + WhatsApp - %')->update(['status' => 'inactive', 'updated_at' => now()]);
        DB::table('category_applications')->where('name', 'Pages + Invoice + WhatsApp')->update(['status' => 'inactive', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Deskripsi lama tidak dikembalikan otomatis. Paket bisa diaktifkan lagi lewat Superadmin › Package.
    }
};

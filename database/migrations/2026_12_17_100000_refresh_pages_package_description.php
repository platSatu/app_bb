<?php

use Database\Seeders\PackageSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 16 Nov 2026: isi Pages berubah -- Bio link & Landing page ditutup, Registrasi ditambah, dan Kuis
 * kini punya PIN/OTP, nilai, hasil lewat WhatsApp serta pembayaran. Deskripsi paket yang SUDAH ada
 * tidak ikut berubah saat seeder dijalankan ulang (firstOrCreate), jadi disegarkan di sini.
 *
 * Hanya deskripsi paket Pages yang ditulis ulang, memakai teks dari PackageSeeder::description().
 * Harga, kuota, masa aktif & status tidak disentuh sama sekali. Paket lain sengaja TIDAK ikut disapu
 * supaya deskripsi yang pernah diedit manual di superadmin tetap utuh.
 */
return new class extends Migration
{
    private const PLAN = 'Pages';

    /** Nama baku buatan seeder; paket yang dibuat manual di superadmin tidak tersentuh. */
    private const LABELS = ['Trial 7 Hari', '6 Bulan', '12 Bulan', 'Gratis Selamanya'];

    public function up(): void
    {
        $config = PackageSeeder::PLANS[self::PLAN] ?? null;

        if (! $config) {
            return;
        }

        $names = array_map(fn ($label) => self::PLAN.' - '.$label, self::LABELS);

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

    public function down(): void
    {
        // Deskripsi lama tidak dikembalikan otomatis; bisa diedit di Superadmin › Package.
    }
};

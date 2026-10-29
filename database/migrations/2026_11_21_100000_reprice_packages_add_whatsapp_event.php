<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Susunan paket baru (29 Okt 2026):
 *   - Komplit: 6 bulan Rp2.694.000, 12 bulan Rp4.788.000 (Rp399.000/bulan).
 *   - Pages: 6 & 12 bulan gratis.
 *   - Kategori baru WhatsApp & Event (Ticket) dibuat lewat seeder (sekali, saat deploy):
 *       php artisan db:seed --class=PackageSeeder --force
 *
 * Hanya harga JUAL paket yang diubah. Langganan & voucher yang sudah dibeli tidak tersentuh
 * (harga yang dibayar tersimpan di voucher masing-masing). Paket lain tetap.
 */
return new class extends Migration
{
    private const PRICES = [
        'Komplit - 6 Bulan' => 2694000,
        'Komplit - 12 Bulan' => 4788000,
        'Pages - 6 Bulan' => 0,
        'Pages - 12 Bulan' => 0,
    ];

    public function up(): void
    {
        foreach (self::PRICES as $name => $price) {
            DB::table('packages')->where('name', $name)->update(['price' => $price, 'updated_at' => now()]);
        }

        // Paket gratis tidak ditandai Populer.
        DB::table('packages')->where('name', 'Pages - 12 Bulan')->update(['is_featured' => false]);
    }

    public function down(): void
    {
        // Tidak dikembalikan otomatis (harga lama bisa saja sudah diedit superadmin).
        // Kalau perlu, ubah lewat Superadmin › Package.
    }
};

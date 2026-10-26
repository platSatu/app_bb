<?php

namespace Database\Seeders;

use App\Models\CategoryApplication;
use App\Models\Package;
use Illuminate\Database\Seeder;

/**
 * Paket awal bizbos: 5 kategori (tab di halaman Beli Paket) x (Trial 7 hari, 6 bulan, 12 bulan). 12 bulan ditandai Populer.
 * Jalankan: php artisan db:seed --class=PackageSeeder
 * Aman dijalankan ulang: harga, fitur, kuota, dll. yang sudah diedit di superadmin TIDAK ditimpa.
 * Hanya kategorinya yang selalu disamakan (paket dari seeder versi lama masuk ke satu kategori).
 */
class PackageSeeder extends Seeder
{
    // Harga per durasi (hari => rupiah). Acuan harga normal per bulan: 6 bulan hemat ~10%, 12 bulan hemat ~20%.
    // Batas per bulan: invoices = invoice dibuat, whatsapp = pesan terkirim. Tidak diisi = tanpa batas.
    private const PLANS = [
        'Komplit' => [
            'apps' => ['pages', 'schedules', 'invoices', 'whatsapp'],
            'prices' => [180 => 1349000, 365 => 2399000],
            'limits' => ['invoices' => 2000, 'whatsapp' => 20000],
        ],
        'Pages' => [
            'apps' => ['pages'],
            'prices' => [180 => 429000, 365 => 759000],
            'limits' => [],
        ],
        'Invoice' => [
            'apps' => ['invoices'],
            'prices' => [180 => 539000, 365 => 949000],
            'limits' => ['invoices' => 500],
        ],
        'Invoice + WhatsApp' => [
            'apps' => ['invoices', 'whatsapp'],
            'prices' => [180 => 799000, 365 => 1429000],
            'limits' => ['invoices' => 500, 'whatsapp' => 5000],
        ],
        'Pages + Invoice + WhatsApp' => [
            'apps' => ['pages', 'invoices', 'whatsapp'],
            'prices' => [180 => 1079000, 365 => 1899000],
            'limits' => ['invoices' => 1000, 'whatsapp' => 10000],
        ],
    ];

    private const TRIAL_LIMITS = ['invoices' => 20, 'whatsapp' => 200];

    private const DURATIONS = [7 => 'Trial 7 Hari', 180 => '6 Bulan', 365 => '12 Bulan'];

    private const FEATURES = [
        'pages' => 'Pages: landing page, bio link, katalog online, tiket event & kuis',
        'schedules' => 'Jadwal: jadwal rutin, absensi, portal murid & pengajar',
        'invoices' => 'Invoice: link bayar online, invoice periode, disbursement & API',
        'whatsapp' => 'WhatsApp: inbox tim CS, broadcast, auto reply & chatbot',
    ];

    public function run(): void
    {
        foreach (self::PLANS as $plan => $config) {
            $category = CategoryApplication::firstOrCreate(['name' => $plan], ['status' => 'active']);

            foreach (self::DURATIONS as $days => $label) {
                $trial = $days === 7;
                $limits = $trial ? array_intersect_key(self::TRIAL_LIMITS, $config['limits']) : $config['limits'];

                $package = Package::firstOrCreate(['name' => "{$plan} - {$label}"], [
                    'category_application_id' => $category->id,
                    'price' => $trial ? 0 : $config['prices'][$days],
                    'duration' => $days,
                    'apps' => $config['apps'],
                    'limits' => array_map(fn ($max) => ['monthly' => $max], $limits),
                    'description' => $this->features($config['apps'], $limits),
                    'is_trial' => $trial,
                    'is_featured' => $days === 365,
                    'status' => 'active',
                ]);

                if ($package->category_application_id !== $category->id) {
                    $package->update(['category_application_id' => $category->id]);
                }
            }
        }
    }

    /** Satu baris per layanan (kuota ikut di barisnya), supaya muat di 5 poin pertama kartu katalog. */
    private function features(array $apps, array $limits): string
    {
        $units = ['invoices' => 'invoice', 'whatsapp' => 'pesan'];
        $lines = array_map(fn ($app) => self::FEATURES[$app]
            .(isset($limits[$app]) ? ' ('.number_format($limits[$app], 0, ',', '.').' '.$units[$app].'/bulan)' : ''), $apps);

        return implode("\n", [...$lines, 'Data customer & export Excel']);
    }
}

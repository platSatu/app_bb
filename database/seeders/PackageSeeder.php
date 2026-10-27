<?php

namespace Database\Seeders;

use App\Models\CategoryApplication;
use App\Models\Package;
use Illuminate\Database\Seeder;

/**
 * Paket awal bizbos: 5 kategori (tab di halaman Beli Paket) x (Trial 7 hari, 6 bulan, 12 bulan). 12 bulan ditandai Populer.
 * Jalankan: php artisan db:seed --class=PackageSeeder
 * Aman dijalankan ulang: harga, fitur, kuota, dll. yang sudah diedit di superadmin TIDAK ditimpa.
 * Fitur = ringkasan per layanan (5 baris pertama, tampil di kartu) + rincian sesuai menu yang ada.
 * Paket yang deskripsinya masih ringkasan versi lama (belum pernah diedit) dilengkapi otomatis.
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

    /** Rincian per layanan, mengikuti menu di sidebar (config/menu.php). */
    private const DETAILS = [
        'pages' => [
            'Landing page & bio link tanpa coding',
            'Katalog online dengan pembayaran',
            'Tiket event, e-ticket QR & scan registrasi ulang',
            'ID card peserta',
            'Form pendaftaran dengan pembayaran',
            'Kuis & tes online dengan skor otomatis',
            'Pengunjung yang mengisi otomatis jadi customer',
        ],
        'schedules' => [
            'Jadwal rutin mingguan, sesi dibuat otomatis',
            'Cek bentrok jadwal pengajar & ruangan',
            'Absensi, izin & sesi pengganti',
            'Data murid, pengajar, ruangan & kelas',
            'Honor pengajar tercatat dari absensi',
            'Link pendaftaran murid & pengajar',
            'Portal orang tua/murid & pengajar',
        ],
        'invoices' => [
            'Link bayar online: Virtual Account, e-wallet, QRIS',
            'Invoice periode untuk banyak pelanggan sekaligus',
            'Kategori & daftar pelanggan berlangganan',
            'Pengingat jatuh tempo otomatis',
            'Denda keterlambatan otomatis',
            'Halaman Tagihan Saya untuk pelanggan',
            'Kirim dana ke banyak rekening (disbursement)',
            'API & webhook untuk developer',
        ],
        'whatsapp' => [
            'Hubungkan banyak nomor dengan scan QR',
            'Inbox bersama: bagi chat, label & catatan',
            'Broadcast & pesan terjadwal',
            'Chatbot menu & auto reply kata kunci',
            'AI bot dengan pengetahuan bisnis Anda',
            'Template pesan & quick reply',
            'Import kontak, grup & grup WhatsApp',
            'Integrasi Google Form & API',
        ],
    ];

    public function run(): void
    {
        foreach (self::PLANS as $plan => $config) {
            $category = CategoryApplication::firstOrCreate(['name' => $plan], ['status' => 'active']);

            // Paket 1 bulan dari seeder versi lama tidak dijual lagi: dihapus, atau dinonaktifkan kalau sudah pernah dibeli.
            Package::where('name', "{$plan} - 1 Bulan")->doesntHave('vouchers')->delete();
            Package::where('name', "{$plan} - 1 Bulan")->update(['status' => Package::STATUS_INACTIVE]);

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

                // Masih ringkasan versi lama (belum diedit superadmin): lengkapi dengan rincian fitur.
                if ($package->description === $this->summary($config['apps'], $limits)) {
                    $package->update(['description' => $this->features($config['apps'], $limits)]);
                }
            }
        }
    }

    /** Ringkasan (5 baris pertama kartu katalog) lalu rincian fitur setiap layanan. */
    private function features(array $apps, array $limits): string
    {
        return implode("\n", [$this->summary($apps, $limits), ...array_merge(...array_map(fn ($app) => self::DETAILS[$app], $apps))]);
    }

    /** Satu baris per layanan (kuota ikut di barisnya), supaya muat di 5 poin pertama kartu katalog. */
    private function summary(array $apps, array $limits): string
    {
        $units = ['invoices' => 'invoice', 'whatsapp' => 'pesan'];
        $lines = array_map(fn ($app) => self::FEATURES[$app]
            .(isset($limits[$app]) ? ' ('.number_format($limits[$app], 0, ',', '.').' '.$units[$app].'/bulan)' : ''), $apps);

        return implode("\n", [...$lines, 'Data customer & export Excel']);
    }
}

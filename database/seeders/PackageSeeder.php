<?php

namespace Database\Seeders;

use App\Models\CategoryApplication;
use App\Models\Package;
use Illuminate\Database\Seeder;

/**
 * Paket bizbos: 7 kategori (tab di halaman Beli Paket) x (Trial 7 hari, 6 bulan, 12 bulan). 12 bulan ditandai Populer.
 * 29 Okt 2026: harga Komplit dihitung ulang, Pages gratis, tambah WhatsApp & Event (Ticket).
 * 6 Nov 2026: Pages & Event (Ticket) berdiri sendiri dan masing-masing hanya SATU paket "Gratis Selamanya"
 * (harga 0, tanpa masa aktif, Package::LIFETIME). Paket lama Pages / Event (Trial, 6 & 12 bulan) dihapus,
 * atau dinonaktifkan kalau sudah pernah dibeli.
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
        // 29 Okt 2026: Komplit Rp399.000/bulan (12 bulan). Lebih hemat dibanding beli layanan satu-satu
        // dengan kuota yang sama (Invoice 4x500 + WhatsApp 4x5.000 > Rp700.000/bulan), plus Jadwal.
        'Komplit' => [
            'apps' => ['events', 'pages', 'schedules', 'invoices', 'whatsapp'],
            'prices' => [180 => 2694000, 365 => 4788000],
            'limits' => ['invoices' => 2000, 'whatsapp' => 20000],
        ],
        // 6 Nov 2026: Pages saja (Event terpisah), gratis selamanya.
        'Pages' => [
            'apps' => ['pages'],
            'lifetime' => true,
            'limits' => [],
            'notes' => ['Gratis selamanya, tanpa masa aktif'],
        ],
        'Invoice' => [
            'apps' => ['invoices'],
            'prices' => [180 => 539000, 365 => 949000],
            'limits' => ['invoices' => 500],
        ],
        // 29 Okt 2026: paket baru WhatsApp saja, ±Rp99.000/bulan (12 bulan).
        'WhatsApp' => [
            'apps' => ['whatsapp'],
            'prices' => [180 => 654000, 365 => 1188000],
            'limits' => ['whatsapp' => 5000],
        ],
        'Invoice + WhatsApp' => [
            'apps' => ['invoices', 'whatsapp'],
            'prices' => [180 => 799000, 365 => 1429000],
            'limits' => ['invoices' => 500, 'whatsapp' => 5000],
        ],
        'Pages + Invoice + WhatsApp' => [
            'apps' => ['events', 'pages', 'invoices', 'whatsapp'],
            'prices' => [180 => 1079000, 365 => 1899000],
            'limits' => ['invoices' => 1000, 'whatsapp' => 10000],
        ],
        // 29 Okt 2026: paket promosi Event (Ticket), bukan langganan berbayar. Biaya tiketnya diatur
        // superadmin di Biaya Layanan Tiket (event gratis maks. 1.000 tiket, event berbayar 1%/tiket).
        // 6 Nov 2026: gratis selamanya, tanpa masa aktif.
        'Event (Ticket)' => [
            'apps' => ['events'],
            'lifetime' => true,
            'limits' => [],
            'notes' => [
                'Gratis selamanya, tanpa masa aktif',
                'Gratis 1.000 tiket untuk event gratis',
                'Event berbayar: biaya layanan hanya 1% per tiket terjual',
                'Tanpa biaya langganan',
            ],
        ],
    ];

    private const TRIAL_LIMITS = ['invoices' => 20, 'whatsapp' => 200];

    private const DURATIONS = [7 => 'Trial 7 Hari', 180 => '6 Bulan', 365 => '12 Bulan'];

    private const LIFETIME_LABEL = 'Gratis Selamanya';

    private const FEATURES = [
        'events' => 'Event: tiket event, e-ticket QR, registrasi ulang & ID card',
        'pages' => 'Pages: landing page, bio link, katalog online & kuis',
        'schedules' => 'Jadwal: jadwal rutin, absensi, portal murid & pengajar',
        'invoices' => 'Invoice: link bayar online, invoice periode, disbursement & API',
        'whatsapp' => 'WhatsApp: inbox tim CS, broadcast, auto reply & chatbot',
    ];

    /** Rincian per layanan, mengikuti menu di sidebar (config/menu.php). */
    // Paket yang membuka Event + Pages sekaligus diringkas jadi satu baris (sama dengan ringkasan versi lama).
    private const FEATURES_EVENTS_PAGES = 'Pages: landing page, bio link, katalog online, tiket event & kuis';

    private const DETAILS = [
        'events' => [
            'Tiket event, e-ticket QR & scan registrasi ulang',
            'ID card peserta',
            'Form pendaftaran dengan pembayaran',
        ],
        'pages' => [
            'Landing page & bio link tanpa coding',
            'Katalog online dengan pembayaran',
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

            if ($config['lifetime'] ?? false) {
                $this->lifetime($plan, $config, $category);

                continue;
            }

            foreach (self::DURATIONS as $days => $label) {
                $trial = $days === 7;
                $limits = $trial ? array_intersect_key(self::TRIAL_LIMITS, $config['limits']) : $config['limits'];

                $package = Package::firstOrCreate(['name' => "{$plan} - {$label}"], [
                    'category_application_id' => $category->id,
                    'price' => $trial ? 0 : $config['prices'][$days],
                    'duration' => $days,
                    'apps' => $config['apps'],
                    'limits' => array_map(fn ($max) => ['monthly' => $max], $limits),
                    'description' => $this->features($config['apps'], $limits, $config['notes'] ?? []),
                    'is_trial' => $trial,
                    // Populer hanya untuk paket 12 bulan berbayar (paket gratis tidak perlu ditandai).
                    'is_featured' => $days === 365 && $config['prices'][365] > 0,
                    'status' => 'active',
                ]);

                if ($package->category_application_id !== $category->id) {
                    $package->update(['category_application_id' => $category->id]);
                }

                // Masih ringkasan versi lama (belum diedit superadmin): lengkapi dengan rincian fitur.
                if ($package->description === $this->summary($config['apps'], $limits)) {
                    $package->update(['description' => $this->features($config['apps'], $limits, $config['notes'] ?? [])]);
                }
            }
        }
    }

    /** Satu paket gratis tanpa masa aktif; paket berdurasi lama kategori ini tidak dijual lagi. */
    private function lifetime(string $plan, array $config, CategoryApplication $category): void
    {
        // Hanya paket buatan seeder (nama baku); paket yang dibuat manual di superadmin tidak disentuh.
        $old = Package::whereIn('name', array_map(fn ($label) => "{$plan} - {$label}", ['1 Bulan', ...array_values(self::DURATIONS)]));
        (clone $old)->doesntHave('vouchers')->delete();
        $old->update(['status' => Package::STATUS_INACTIVE]);

        Package::firstOrCreate(['name' => "{$plan} - ".self::LIFETIME_LABEL], [
            'category_application_id' => $category->id,
            'price' => 0,
            'duration' => Package::LIFETIME,
            'apps' => $config['apps'],
            'limits' => [],
            'description' => $this->features($config['apps'], [], $config['notes'] ?? []),
            'is_trial' => false,
            'is_featured' => false,
            'status' => 'active',
        ]);
    }

    /** Ringkasan (5 baris pertama kartu katalog) lalu rincian fitur setiap layanan. */
    private function features(array $apps, array $limits, array $notes = []): string
    {
        return implode("\n", [$this->summary($apps, $limits), ...$notes, ...array_merge(...array_map(fn ($app) => self::DETAILS[$app], $apps))]);
    }

    /** Satu baris per layanan (kuota ikut di barisnya), supaya muat di 5 poin pertama kartu katalog. */
    private function summary(array $apps, array $limits): string
    {
        $units = ['invoices' => 'invoice', 'whatsapp' => 'pesan'];
        $both = in_array('events', $apps, true) && in_array('pages', $apps, true);
        $lines = array_map(fn ($app) => ($both && $app === 'pages' ? self::FEATURES_EVENTS_PAGES : self::FEATURES[$app])
            .(isset($limits[$app]) ? ' ('.number_format($limits[$app], 0, ',', '.').' '.$units[$app].'/bulan)' : ''), $both ? array_values(array_diff($apps, ['events'])) : $apps);

        return implode("\n", [...$lines, 'Data customer & export Excel']);
    }
}

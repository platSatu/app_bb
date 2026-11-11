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
 * Fitur = catatan + ringkasan per layanan (5 baris pertama, tampil di kartu) + rincian sesuai menu yang ada.
 * Hanya kategorinya yang selalu disamakan (paket dari seeder versi lama masuk ke satu kategori).
 */
class PackageSeeder extends Seeder
{
    // Harga per durasi (hari => rupiah). Acuan harga normal per bulan: 6 bulan hemat ~10%, 12 bulan hemat ~20%.
    // Batas per bulan: invoices = invoice dibuat, whatsapp = pesan terkirim. Tidak diisi = tanpa batas.
    // 11 Des 2026: Pages & Event (Ticket) gratis dipakai sendiri; paket berbayar: Invoice, WhatsApp, Invoice + WhatsApp, Komplit.
    // Paket "Pages + Invoice + WhatsApp" tidak dijual lagi (dinonaktifkan lewat migration 2026_12_09_100000).
    public const PLANS = [
        // Komplit: Jadwal les/kursus + Invoice + WhatsApp (Pages & Event ikut terbuka).
        'Komplit' => [
            'apps' => ['events', 'pages', 'schedules', 'invoices', 'whatsapp'],
            'prices' => [180 => 2694000, 365 => 4788000],
            'limits' => ['invoices' => 2000, 'whatsapp' => 20000],
        ],
        'Pages' => [
            'apps' => ['pages'],
            'lifetime' => true,
            'limits' => [],
            'notes' => ['Gratis selamanya, tanpa biaya langganan'],
        ],
        'Invoice' => [
            'apps' => ['invoices'],
            'prices' => [180 => 539000, 365 => 949000],
            'limits' => ['invoices' => 500],
        ],
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
        // Tanpa langganan; biaya per tiket diatur superadmin di Biaya Layanan Tiket ({biaya_tiket} & {tiket_gratis}
        // diganti otomatis saat tampil, lihat Package::describe()).
        'Event (Ticket)' => [
            'apps' => ['events'],
            'lifetime' => true,
            'limits' => [],
            'notes' => [
                'Tanpa biaya langganan, bayar hanya saat tiket terjual',
                'Event berbayar: biaya layanan {biaya_tiket} per tiket',
                'Event gratis: sampai {tiket_gratis} tiket tanpa biaya, cocok untuk ibadah, komunitas & acara kecil',
            ],
        ],
    ];

    private const TRIAL_LIMITS = ['invoices' => 20, 'whatsapp' => 200];

    private const DURATIONS = [7 => 'Trial 7 Hari', 180 => '6 Bulan', 365 => '12 Bulan'];

    private const LIFETIME_LABEL = 'Gratis Selamanya';

    /** Layanan yang gratis dipakai sendiri; di paket berbayar cukup disebut satu baris. */
    private const FREE_APPS = ['events', 'pages'];

    /** Satu baris ringkasan per layanan (5 baris pertama tampil di kartu katalog). */
    private const FEATURES = [
        'events' => 'Event: e-ticket QR, registrasi ulang & ID card peserta',
        'pages' => 'Pages: bio link, katalog online, landing page & kuis',
        'schedules' => 'Jadwal les & kursus: kelas bulanan atau paket pertemuan',
        'invoices' => 'Invoice: tagihan online dengan link bayar VA, QRIS & e-wallet',
        'whatsapp' => 'WhatsApp: inbox tim CS, broadcast, auto reply & chatbot',
    ];

    /** Rincian per layanan, bahasa sehari-hari (tanpa istilah teknis). */
    private const DETAILS = [
        'events' => [
            'E-ticket QR dikirim otomatis ke email pembeli',
            'Scan registrasi ulang & cetak ID card peserta',
            'Form pendaftaran dengan pembayaran online',
        ],
        'pages' => [
            'Bio link: semua link penting dalam satu halaman',
            'Katalog online: pembeli pesan & bayar langsung',
            'Landing page tanpa coding, tinggal isi',
            'Kuis & tes online dengan skor otomatis',
            'Pengunjung yang mengisi otomatis tersimpan jadi customer',
        ],
        'schedules' => [
            'Kelas bulanan atau paket (mis. 15x pertemuan) dengan pola bebas',
            'Absensi fleksibel: pilih manual atau scan kartu QR murid',
            'Kartu absen QR lewat portal, email & WhatsApp',
            'Tagihan les dibuat otomatis ke Invoice, tanpa input ulang',
            'Pengingat les H-1 lewat WhatsApp',
            'Dashboard & portal untuk murid/orang tua dan pengajar',
            'Rekap bulanan, honor pengajar & export Excel',
            'Cek bentrok jadwal pengajar & ruangan otomatis',
            'Murid masuk/keluar di tengah bulan tercatat rapi',
        ],
        'invoices' => [
            'Tagih banyak pelanggan sekaligus per periode',
            'Pengingat jatuh tempo & denda keterlambatan otomatis',
            'Pelanggan bisa lihat & bayar di halaman Tagihan Saya',
            'Kirim dana ke banyak rekening (disbursement)',
            'Kategori & daftar pelanggan berlangganan',
        ],
        'whatsapp' => [
            'Hubungkan banyak nomor cukup dengan scan QR',
            'Inbox bersama: bagi chat, label & catatan',
            'Broadcast & pesan terjadwal',
            'Chatbot menu, auto reply & AI bot yang paham bisnis Anda',
            'Notifikasi otomatis ke pelanggan, mis. tagihan & e-ticket',
            'Template pesan & quick reply',
            'Import kontak, kelompok kontak & grup WhatsApp',
            'Terhubung dengan Google Form',
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
                    'description' => self::description($config['apps'], $limits, [...($trial ? ['Coba gratis 7 hari'] : []), ...($config['notes'] ?? [])]),
                    'is_trial' => $trial,
                    // Populer hanya untuk paket 12 bulan berbayar (paket gratis tidak perlu ditandai).
                    'is_featured' => $days === 365 && $config['prices'][365] > 0,
                    'status' => 'active',
                ]);

                if ($package->category_application_id !== $category->id) {
                    $package->update(['category_application_id' => $category->id]);
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
            'description' => self::description($config['apps'], [], $config['notes'] ?? []),
            'is_trial' => false,
            'is_featured' => false,
            'status' => 'active',
        ]);
    }

    /**
     * Deskripsi paket (satu fitur per baris): catatan, ringkasan per layanan berbayar + kuota, lalu rinciannya.
     * Pages & Event yang ikut di paket berbayar cukup disebut satu baris karena memang gratis dipakai sendiri.
     * Dipakai juga migration 2026_12_09_100000 untuk menyegarkan deskripsi paket yang sudah ada.
     */
    public static function description(array $apps, array $limits, array $notes = []): string
    {
        $units = ['invoices' => 'invoice', 'whatsapp' => 'pesan'];
        $paid = array_values(array_diff($apps, self::FREE_APPS)) ?: $apps;
        $summary = array_map(fn ($app) => self::FEATURES[$app]
            .(isset($limits[$app]) ? ' ('.number_format($limits[$app], 0, ',', '.').' '.$units[$app].'/bulan)' : ''), $paid);

        return implode("\n", [
            ...$notes,
            ...$summary,
            'Data customer & export Excel',
            ...array_merge(...array_map(fn ($app) => self::DETAILS[$app], $paid)),
            ...($paid !== $apps ? ['Pages & Event (Ticket) juga bisa dipakai, gratis'] : []),
        ]);
    }
}

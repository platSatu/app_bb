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
 * 16 Nov 2026: isi Pages berubah -- Bio link & Landing page ditutup, Registrasi ditambah, Kuis kini
 * punya PIN/OTP, nilai & pembayaran. Harga tidak berubah; deskripsi paket yang sudah ada disegarkan
 * lewat migration 2026_12_17_100000.
 * 25 Nov 2026: Komplit diganti paket Kursus per jumlah murid aktif (Starter 50, Growth 150, Pro 400, Unlimited),
 * bisa bulanan / 6 bulan / 12 bulan, trial 30 hari (30 murid). Paket Komplit dihapus, atau dinonaktifkan kalau
 * sudah pernah dibeli; langganan Komplit yang sedang berjalan tetap aktif sampai habis.
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
    // 25 Nov 2026: Komplit diganti Kursus Starter/Growth/Pro/Unlimited. Batas schedules = murid aktif sekaligus (bukan per bulan).
    // Tanpa 'trial' = Trial 7 hari dengan TRIAL_LIMITS; 'trial' => false = tanpa trial.
    // Paket "Pages + Invoice + WhatsApp" tidak dijual lagi (dinonaktifkan lewat migration 2026_12_09_100000).
    private const COURSE_APPS = ['events', 'pages', 'schedules', 'invoices', 'whatsapp'];

    /** Paket lama yang tidak dijual lagi (semua durasinya). */
    public const RETIRED = ['Komplit'];

    public const PLANS = [
        // Kursus (25 Nov 2026, menggantikan Komplit): Jadwal + Invoice + WhatsApp (Pages & Event ikut terbuka),
        // dibedakan dari jumlah murid aktif sekaligus (PlanLimits::assertStudents). Kuota WhatsApp ~30 pesan/murid,
        // invoice ~2/murid per bulan. Trial 30 hari hanya di Starter (trial dipakai sekali per akun).
        'Kursus Starter' => [
            'apps' => self::COURSE_APPS,
            'prices' => [30 => 99000, 180 => 535000, 365 => 990000],
            'limits' => ['schedules' => 50, 'invoices' => 100, 'whatsapp' => 1500],
            'trial' => ['days' => 30, 'limits' => ['schedules' => 30, 'invoices' => 60, 'whatsapp' => 300]],
        ],
        'Kursus Growth' => [
            'apps' => self::COURSE_APPS,
            'prices' => [30 => 199000, 180 => 1075000, 365 => 1990000],
            'limits' => ['schedules' => 150, 'invoices' => 300, 'whatsapp' => 4500],
            'trial' => false,
        ],
        'Kursus Pro' => [
            'apps' => self::COURSE_APPS,
            'prices' => [30 => 349000, 180 => 1885000, 365 => 3490000],
            'limits' => ['schedules' => 400, 'invoices' => 800, 'whatsapp' => 12000],
            'trial' => false,
        ],
        'Kursus Unlimited' => [
            'apps' => self::COURSE_APPS,
            'prices' => [30 => 599000, 180 => 3235000, 365 => 5990000],
            'limits' => ['invoices' => 2000, 'whatsapp' => 20000],
            'notes' => ['Murid aktif tanpa batas'],
            'trial' => false,
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

    /** Label durasi berbayar; 30 hari = paket bulanan (paket Kursus). */
    private const PAID_LABELS = [30 => 'Bulanan', 180 => '6 Bulan', 365 => '12 Bulan'];

    private const LIFETIME_LABEL = 'Gratis Selamanya';

    /** Layanan yang gratis dipakai sendiri; di paket berbayar cukup disebut satu baris. */
    private const FREE_APPS = ['events', 'pages'];

    /** Satu baris ringkasan per layanan (5 baris pertama tampil di kartu katalog). */
    private const FEATURES = [
        'events' => 'Event: e-ticket QR, registrasi ulang & ID card peserta',
        'pages' => 'Pages: katalog online, kuis berbayar & form pendaftaran',
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
            'Katalog online: pembeli pesan & bayar langsung',
            'Kuis & tes online dengan skor dan nilai otomatis',
            'Kuis berbayar: peserta bayar dulu, uangnya langsung masuk saldo',
            'Kuis bisa dikunci PIN; kode & hasil lewat WhatsApp bila berlangganan WhatsApp',
            'Form pendaftaran: peserta daftar, datanya rapi & bisa diunduh Excel',
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

            $trial = $config['trial'] ?? ['days' => 7, 'limits' => array_intersect_key(self::TRIAL_LIMITS, $config['limits'])];
            $variants = $trial ? [['days' => $trial['days'], 'label' => "Trial {$trial['days']} Hari", 'trial' => true, 'limits' => $trial['limits']]] : [];
            foreach ($config['prices'] as $days => $price) {
                $variants[] = ['days' => $days, 'label' => self::PAID_LABELS[$days], 'trial' => false, 'limits' => $config['limits'], 'price' => $price];
            }

            foreach ($variants as $variant) {
                $notes = [...($variant['trial'] ? ["Coba gratis {$variant['days']} hari"] : []), ...($config['notes'] ?? [])];

                $package = Package::firstOrCreate(['name' => "{$plan} - {$variant['label']}"], [
                    'category_application_id' => $category->id,
                    'price' => $variant['trial'] ? 0 : $variant['price'],
                    'duration' => $variant['days'],
                    'apps' => $config['apps'],
                    'limits' => self::limits($variant['limits']),
                    'description' => self::description($config['apps'], $variant['limits'], $notes),
                    'is_trial' => $variant['trial'],
                    // Populer hanya untuk paket 12 bulan berbayar (paket gratis tidak perlu ditandai).
                    'is_featured' => $variant['days'] === 365 && ! $variant['trial'] && $variant['price'] > 0,
                    'status' => 'active',
                ]);

                if ($package->category_application_id !== $category->id) {
                    $package->update(['category_application_id' => $category->id]);
                }
            }
        }

        $this->retire();
    }

    /**
     * Paket lama (RETIRED) tidak dijual lagi: dihapus kalau belum pernah dibeli, selain itu dinonaktifkan beserta tabnya.
     * Langganan branch yang sedang berjalan tidak disentuh (batasnya sudah tersalin di branch).
     */
    private function retire(): void
    {
        foreach (self::RETIRED as $plan) {
            $labels = ['1 Bulan', 'Bulanan', ...array_values(self::DURATIONS)];
            $old = Package::whereIn('name', array_map(fn ($label) => "{$plan} - {$label}", $labels));
            (clone $old)->doesntHave('vouchers')->delete();
            $old->update(['status' => Package::STATUS_INACTIVE]);
            CategoryApplication::where('name', $plan)->update(['status' => 'inactive']);
        }
    }

    /** Batas paket dari angka sederhana: schedules = murid aktif sekaligus, lainnya per bulan. */
    public static function limits(array $limits): array
    {
        $result = [];
        foreach ($limits as $app => $max) {
            $result[$app] = [$app === 'schedules' ? 'students' : 'monthly' => $max];
        }

        return $result;
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
        $units = ['invoices' => 'invoice/bulan', 'whatsapp' => 'pesan/bulan', 'schedules' => 'murid aktif'];
        $paid = array_values(array_diff($apps, self::FREE_APPS)) ?: $apps;
        $summary = array_map(fn ($app) => self::FEATURES[$app]
            .(isset($limits[$app]) ? ' ('.($app === 'schedules' ? 'sampai ' : '').number_format($limits[$app], 0, ',', '.').' '.$units[$app].')' : ''), $paid);

        return implode("\n", [
            ...$notes,
            ...$summary,
            'Data customer & export Excel',
            ...array_merge(...array_map(fn ($app) => self::DETAILS[$app], $paid)),
            ...($paid !== $apps ? ['Pages & Event (Ticket) juga bisa dipakai, gratis'] : []),
        ]);
    }
}

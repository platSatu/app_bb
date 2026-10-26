<?php

namespace Database\Seeders;

use App\Models\WebFeature;
use App\Support\Web\SortOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * 12 fitur unggulan untuk section Fitur di website (Superadmin > Web > Fitur), urut sesuai pola bento grid
 * fe-konexa (kotak pertama paling besar). Thumbnail PNG transparan ada di database/seeders/assets/features.
 * Jalankan: php artisan db:seed --class=WebFeatureSeeder
 * Aman dijalankan ulang: fitur yang namanya sudah ada tidak ditimpa; gambarnya hanya diisi kalau masih kosong.
 */
class WebFeatureSeeder extends Seeder
{
    private const FEATURES = [
        'dashboard' => ['Semua Bisnis dalam Satu Dashboard', 'Pages, jadwal, tagihan, dan WhatsApp dikelola dari satu akun untuk semua cabang Anda.'],
        'inbox' => ['Inbox WhatsApp untuk Tim', 'Satu nomor WhatsApp dipegang banyak CS sekaligus, lengkap dengan label, catatan, dan pembagian chat.'],
        'chatbot' => ['Chatbot & Auto Reply', 'Pelanggan tetap dijawab 24 jam lewat chatbot, auto reply, dan quick reply.'],
        'broadcast' => ['Broadcast Terjadwal', 'Kirim pesan promo ke ribuan kontak, bisa dijeda dan dilanjutkan kapan saja.'],
        'invoice' => ['Invoice & Link Bayar', 'Buat tagihan satuan atau bulanan, customer bayar online, status lunas tercatat otomatis.'],
        'reminder' => ['Pengingat Tagihan Otomatis', 'Customer diingatkan sebelum dan sesudah jatuh tempo, tanpa perlu ditagih manual.'],
        'transfer' => ['Kirim Dana ke Banyak Rekening', 'Bayar vendor atau mitra langsung dari saldo, nama rekening dicek otomatis ke bank.'],
        'biolink' => ['Landing Page & Bio Link', 'Buat halaman jualan dan link-in-bio sendiri tanpa coding, siap dibagikan.'],
        'ticket' => ['Tiket Event dengan Scan QR', 'Jual tiket, cetak ID card peserta, dan scan QR di pintu masuk dalam satu alur.'],
        'catalog' => ['Katalog Online', 'Pembeli pilih produk dan bayar online, pesanan langsung masuk.'],
        'schedule' => ['Jadwal Anti Bentrok', 'Jadwal kelas, pengajar, dan ruangan dicek otomatis supaya tidak tabrakan.'],
        'integration' => ['Integrasi API & Google Form', 'Sambungkan website atau Google Form Anda, pesan WhatsApp terkirim otomatis.'],
    ];

    public function run(): void
    {
        foreach (self::FEATURES as $key => [$name, $description]) {
            $feature = WebFeature::firstOrCreate(['name' => $name], [
                'description' => $description,
                'status' => 'active',
                'sort_order' => SortOrder::next(WebFeature::query()),
            ]);

            if (! $feature->images) {
                $feature->update(['images' => $this->copyImage($key)]);
            }
        }
    }

    /** Salin thumbnail ke folder upload fitur (public/web/images/features), sama seperti gambar yang diunggah superadmin. */
    private function copyImage(string $key): string
    {
        $relative = "features/bizbos-{$key}.png";
        File::ensureDirectoryExists(public_path('web/images/features'));
        File::copy(database_path("seeders/assets/features/{$key}.png"), public_path("web/images/{$relative}"));

        return $relative;
    }
}

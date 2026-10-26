<?php

namespace Database\Seeders;

use App\Models\WebFaq;
use App\Support\Web\SortOrder;
use Illuminate\Database\Seeder;

/**
 * FAQ website (Superadmin > Website > FAQ), sekaligus sumber jawaban live chat (tanpa AI) dan
 * pengetahuan AI. Disusun per topik; isi mengikuti aturan terbaru (paket, saldo, referral, S&K).
 * Judul sengaja memakai kata yang biasa diketik pengunjung (harga, daftar, gratis, tarik saldo, ...)
 * supaya live chat mudah menemukannya.
 * Jalankan: php artisan db:seed --class=WebFaqSeeder
 * Aman dijalankan ulang: FAQ yang judulnya sudah ada tidak ditimpa.
 */
class WebFaqSeeder extends Seeder
{
    private const FAQS = [
        // Kenalan dengan bizbos
        'Apa itu bizbos?' => 'bizbos adalah satu aplikasi untuk mengurus bisnis Anda sehari-hari: bikin halaman jualan dan tiket event (Pages), atur jadwal kelas dan absensi (Jadwal), kirim tagihan dengan link bayar online (Invoice), sampai melayani pelanggan lewat WhatsApp bersama tim (WhatsApp). Semuanya dari satu akun, untuk satu atau banyak cabang usaha.',
        'bizbos cocok untuk usaha apa saja?' => 'Cocok untuk UMKM, toko online, penyelenggara event, lembaga kursus dan sekolah, jasa, komunitas, sampai perusahaan dengan banyak cabang. Kalau Anda butuh jualan online, menagih pelanggan, mengatur jadwal, atau melayani chat pelanggan, bizbos bisa membantu.',
        'Apakah saya perlu bisa coding untuk memakai bizbos?' => 'Sama sekali tidak perlu. Semua fitur dipakai lewat menu yang sudah siap, cukup isi dan klik. Kalau Anda punya website sendiri, tersedia juga API untuk developer, tapi itu opsional.',

        // Paket & harga
        'Berapa harga paket bizbos?' => 'Paket tersedia untuk 6 bulan dan 12 bulan, mulai dari Pages 6 bulan Rp429.000 (sekitar Rp71 ribuan per bulan). Paket 12 bulan lebih hemat sekitar 20% dan ditandai Populer. Harga lengkap tiap paket bisa dilihat di menu Beli Paket setelah Anda masuk.',
        'Apa saja pilihan paketnya?' => 'Ada 5 pilihan: Pages, Invoice, Invoice + WhatsApp, Pages + Invoice + WhatsApp, dan Komplit (Pages, Jadwal, Invoice, WhatsApp). Masing-masing tersedia untuk 6 bulan dan 12 bulan, plus trial gratis 7 hari. Pilih sesuai kebutuhan, dan Anda bisa menambah paket lain kapan saja.',
        'Bisa coba gratis dulu?' => 'Bisa! Setiap paket punya trial gratis 7 hari tanpa perlu bayar. Trial bisa dipakai satu kali untuk setiap email dan nomor HP. Cukup daftar, buat branch, lalu pilih paket Trial di menu Beli Paket.',
        'Bagaimana cara membeli atau memperpanjang paket?' => 'Isi Saldo terlebih dahulu lewat menu Tambah Saldo, lalu buka Beli Paket, pilih paket, dan klik Bayar dengan Saldo. Paket bisa langsung diaktifkan ke branch Anda, atau disimpan sebagai kode voucher untuk dipakai nanti. Kalau diperpanjang saat masih aktif, masa aktifnya ditambahkan dari tanggal berakhir sebelumnya, jadi tidak ada hari yang hilang.',
        'Apa itu kode voucher paket?' => 'Kode voucher adalah paket yang Anda beli tapi belum diaktifkan, misalnya untuk dipakai nanti atau diberikan ke rekan. Kode berlaku 90 hari sejak dibeli dan bisa diaktifkan lewat menu Punya Kode Voucher.',
        'Apakah ada batas kuota di setiap paket?' => 'Beberapa paket punya batas per bulan, misalnya jumlah invoice dan pesan WhatsApp. Kuota diatur ulang setiap tanggal 1 dan sisa kuota tidak menumpuk ke bulan berikutnya. Rincian kuota tertulis di setiap kartu paket.',
        'Apakah paket bisa direfund?' => 'Paket yang sudah aktif atau kode voucher yang sudah dipakai tidak bisa direfund. Tapi kalau terjadi kesalahan dari sistem kami, misalnya saldo terpotong dua kali, dana pasti kami kembalikan ke Saldo. Laporkan lewat halaman Kontak paling lambat 30 hari ya. Selengkapnya ada di halaman Kebijakan Refund.',

        // Daftar & akun
        'Bagaimana cara daftar akun bizbos?' => 'Klik Daftar, isi nama, email, nomor WhatsApp, dan password, lalu baca dan setujui Syarat & Ketentuan. Setelah itu cek email Anda untuk verifikasi. Bisa juga daftar lebih cepat dengan akun Google.',
        'Kenapa saya harus verifikasi email?' => 'Verifikasi memastikan email itu benar-benar milik Anda, supaya akun dan saldo Anda lebih aman dan informasi penting (seperti pengingat masa aktif) sampai ke Anda. Cek kotak masuk atau folder spam setelah mendaftar.',
        'Saya lupa password, bagaimana?' => 'Tenang, klik Lupa Password di halaman masuk, isi email Anda, lalu ikuti link yang kami kirim untuk membuat password baru. Demi keamanan, semua sesi login lama akan otomatis keluar setelah password diganti.',
        'Apakah saya bisa mengelola beberapa cabang usaha?' => 'Bisa. Di bizbos Anda bisa membuat beberapa branch dalam satu akun, dan setiap branch punya paket serta datanya sendiri. Anda juga bisa mengundang anggota tim dan mengatur menu apa saja yang boleh mereka buka.',

        // Saldo & pembayaran
        'Bagaimana cara isi saldo atau top up?' => 'Buka menu Saldo lalu Tambah Saldo, masukkan nominal, dan bayar lewat Virtual Account, e-wallet, QRIS, atau metode lain yang tersedia. Saldo bertambah otomatis begitu pembayaran dikonfirmasi, biasanya hanya beberapa menit.',
        'Kenapa saldo saya belum bisa ditarik?' => 'Uang yang masuk lewat pembayaran online butuh waktu settlement dari bank dan penyedia pembayaran, dihitung dalam hari kerja. Selama menunggu, saldonya sudah bisa dipakai membeli paket, tapi belum bisa ditarik. Tanggal kapan bisa ditarik terlihat di halaman Tarik Saldo.',
        'Bagaimana cara tarik saldo ke rekening?' => 'Daftarkan rekening atas nama Anda sendiri di menu Tarik Saldo. Nama pemilik rekening dicek ke bank lalu diperiksa tim kami. Setelah rekening disetujui, ajukan penarikan; minimal dan biayanya tampil sebelum Anda konfirmasi. Dana dikirim setelah pengajuan disetujui tim kami.',
        'Berapa lama proses tarik saldo?' => 'Pengajuan diproses tim kami pada jam kerja, lalu dana dikirim langsung ke rekening Anda. Selama menunggu, Anda masih bisa membatalkan pengajuan sendiri dan saldo kembali utuh. Kalau gagal atau ditolak, saldo juga otomatis dikembalikan.',
        'Apakah uang saya aman di bizbos?' => 'Kami menjaga uang Anda dengan berlapis: setiap mutasi saldo tercatat lengkap, pembayaran diproses mitra resmi Duitku, rekening penarikan dicek ke bank, dan setiap penarikan diperiksa tim kami sebelum dikirim.',

        // Referral & affiliate
        'Bagaimana cara dapat komisi dari program referral?' => 'Bagikan link referral dari menu Akun Saya, Referral. Setiap teman yang daftar lewat link Anda lalu membeli atau memperpanjang paket dalam 12 bulan pertama, Anda dapat komisi, default-nya 20% dari harga paket. Anda juga bisa membagi sebagian komisi ke teman sebagai potongan harga.',
        'Kapan komisi referral masuk ke saldo?' => 'Setiap komisi ditahan 30 hari sejak teman Anda membeli, untuk berjaga kalau ada komplain. Setelah itu komisi masuk ke Saldo sekaligus penuh dan bisa dipakai atau ditarik. Satu pembelian teman berarti satu kali pencairan.',
        'Apakah teman yang saya ajak dapat diskon?' => 'Bisa, kalau Anda membagi sebagian komisi Anda di menu Referral. Misalnya komisi Anda 20% dan Anda memberi 5%, teman Anda dapat potongan 5% di setiap pembelian dan Anda tetap mendapat 15%. Potongannya otomatis muncul di halaman pembayaran paket teman Anda.',

        // Fitur
        'Apa saja yang bisa dibuat di Pages?' => 'Di Pages Anda bisa membuat landing page, bio link, katalog online dengan pembayaran, tiket event lengkap dengan scan QR dan ID card peserta, serta kuis dan form pendaftaran. Data pengunjung yang mengisi otomatis tersimpan sebagai customer Anda.',
        'Bagaimana cara menagih pelanggan lewat Invoice?' => 'Buat invoice satuan atau invoice periode untuk banyak pelanggan sekaligus. Pelanggan membayar lewat link bayar online, statusnya otomatis lunas, dan pengingat dikirim sebelum serta sesudah jatuh tempo. Uang masuk ke Saldo Anda setelah dikurangi biaya layanan.',
        'Apakah fitur WhatsApp bisa dipakai banyak CS sekaligus?' => 'Bisa. Satu nomor WhatsApp bisa dibuka beberapa CS di inbox yang sama, lengkap dengan label, catatan, dan pembagian chat. Ada juga broadcast terjadwal, auto reply, quick reply, dan chatbot supaya pelanggan tetap terlayani di luar jam kerja.',
        'Apakah nomor WhatsApp saya bisa diblokir?' => 'bizbos tidak berafiliasi dengan WhatsApp, jadi aturan WhatsApp tetap berlaku. Supaya aman, kirim pesan hanya ke kontak yang sudah memberi izin dan atur jeda broadcast secara wajar. Sediakan juga cara bagi pelanggan untuk berhenti menerima broadcast.',
        'Bisakah bizbos disambungkan ke website atau aplikasi saya?' => 'Bisa. Tersedia API untuk membuat invoice, mengirim dana (Disbursement), dan mengirim pesan WhatsApp dari sistem Anda sendiri, lengkap dengan dokumentasinya. Google Form juga bisa disambungkan supaya setiap pendaftar otomatis dibalas lewat WhatsApp.',

        // Bantuan
        'Bagaimana cara menghubungi tim bizbos?' => 'Kami senang membantu! Hubungi kami lewat tombol WhatsApp di website, halaman Kontak, atau live chat di pojok kanan bawah. Ceritakan kendala Anda dan, kalau ada, sertakan tangkapan layarnya supaya kami bisa membantu lebih cepat.',
        'Di mana saya bisa membaca syarat dan kebijakan bizbos?' => 'Semua dokumen resmi ada di bagian bawah website: Syarat & Ketentuan, Kebijakan Privasi, Kebijakan Penggunaan, Ketentuan Saldo, Kebijakan Refund, dan Program Referral. Kami menulisnya dengan bahasa sederhana supaya mudah dipahami.',
    ];

    public function run(): void
    {
        foreach (self::FAQS as $question => $answer) {
            WebFaq::firstOrCreate(['name' => $question], [
                'descriptions' => $answer,
                'status' => 'active',
                'sort_order' => SortOrder::next(WebFaq::query()),
            ]);
        }
    }
}

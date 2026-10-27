<?php

namespace Database\Seeders;

use App\Models\WebFooter;
use App\Models\WebPage;
use Illuminate\Database\Seeder;

/**
 * Perbarui isi halaman legal & program (Superadmin > Website > Halaman) supaya sesuai cara kerja bizbos sekarang
 * (komisi referral dari pembelian paket, settlement saldo, tarik saldo, voucher paket). Teks ada di
 * database/seeders/content/pages/{slug}.md.
 * Jalankan: php artisan db:seed --class=WebPageContentSeeder
 *
 * Pengaman: halaman hanya diganti kalau isinya MASIH SAMA dengan versi produksi yang menjadi dasar revisi
 * (sidik jari di bawah). Halaman yang sudah diedit lewat superadmin, atau sudah diperbarui, dilewati.
 */
class WebPageContentSeeder extends Seeder
{
    /** slug => [sha1 isi lama, subtitle baru] */
    private const PAGES = [
        'kebijakan-privasi' => ['54d2e38a50045ef467d358259a78629d2e8e1338', 'Bagaimana kami mengumpulkan, memakai, dan melindungi data pribadi Anda.'],
        'kebijakan-penggunaan' => ['e97e5cf16593b715f2a5c79c1b02b0a07e49301e', 'Aturan bersama supaya pengiriman pesan dan pemakaian bizbos tetap aman.'],
        'ketentuan-saldo' => ['53f6cbd6be56e9de7f7f20d924079d2553062393', 'Dari mana saldo berasal, kapan bisa dipakai, dan cara menariknya ke rekening.'],
        'kebijakan-refund' => ['d1fceaf18f752763c11b9a38401e877bae2a4cf7', 'Ketentuan pembelian paket, kode voucher, dan pengembalian dana.'],
        'ketentuan-referral' => ['e92898dc4bddb2aee9c54d5d8a2c95727f6b1d42', 'Cara mendapatkan komisi, berbagi potongan dengan teman, dan kapan komisi cair.'],
        'pemrosesan-data' => ['552ebb903e8ca91fc676ad5aa3c4cd5d58f0c698', 'Pembagian tanggung jawab atas data pelanggan yang Anda kelola di bizbos.'],
        'affiliate' => ['1884c0dd6d1ce4c24e86d7a80e09f7d23a03df42', 'Ajak usaha lain memakai bizbos dan dapatkan komisi dari setiap pembelian mereka.'],
    ];

    /** Sidik jari isi Kebijakan Cookie versi pertama (sebelum ada pilihan "Atur"); hanya versi ini yang diperbarui. */
    private const COOKIE_POLICY_V1 = 'e319ff535a3350b5274e572d5eb5802a8bac3bc5';

    public function run(): void
    {
        $this->addCookiePolicy();

        foreach (self::PAGES as $slug => [$originalHash, $subtitle]) {
            $page = WebPage::where('slug', $slug)->first();

            if (! $page || sha1((string) $page->content) !== $originalHash) {
                $this->command?->line("  dilewati: {$slug} (tidak ada, sudah diperbarui, atau sudah diedit manual)");

                continue;
            }

            $page->update([
                'content' => trim(file_get_contents(database_path("seeders/content/pages/{$slug}.md"))),
                'subtitle' => $subtitle,
                'meta_description' => $subtitle,
            ]);
            $this->command?->info("  diperbarui: {$slug}");
        }
    }

    /**
     * Halaman baru "Kebijakan Cookie" (content/pages/kebijakan-cookie.md) + link di footer website.
     * Tampilan (grup footer, urutan) mengikuti halaman Kebijakan Privasi. Hanya dibuat kalau belum ada,
     * jadi hasil edit superadmin tidak pernah tertimpa.
     */
    private function addCookiePolicy(): void
    {
        $slug = 'kebijakan-cookie';
        $privacy = WebPage::where('slug', 'kebijakan-privasi')->first();

        $content = trim(file_get_contents(database_path("seeders/content/pages/{$slug}.md")));
        $existing = WebPage::where('slug', $slug)->first();

        if ($existing && sha1(str_replace("\r\n", "\n", trim((string) $existing->content))) === self::COOKIE_POLICY_V1) {
            $existing->update(['content' => $content]);
            $this->command?->info('  diperbarui: Kebijakan Cookie');
        }

        if (! $existing) {
            $subtitle = 'Cookie apa saja yang kami pakai, untuk apa, dan cara Anda mengaturnya.';
            WebPage::create([
                'title' => 'Kebijakan Cookie',
                'slug' => $slug,
                'type' => 'document',
                'subtitle' => $subtitle,
                'meta_description' => $subtitle,
                'content' => $content,
                'show_in_footer' => (bool) $privacy?->show_in_footer,
                'footer_group' => $privacy?->footer_group,
                'footer_order' => (int) $privacy?->footer_order + 1,
                'status' => 'active',
            ]);
            $this->command?->info('  dibuat: Kebijakan Cookie');
        }

        $privacyLink = WebFooter::where('link', 'like', '%kebijakan-privasi%')->first();

        if ($privacyLink && ! WebFooter::where('link', 'like', "%{$slug}%")->exists()) {
            WebFooter::create([
                'name' => 'Kebijakan Cookie',
                'link' => str_replace('kebijakan-privasi', $slug, $privacyLink->link),
                'group_name' => $privacyLink->group_name,
                'column_width' => $privacyLink->column_width,
                'target_blank' => $privacyLink->target_blank,
                'sort_order' => (int) $privacyLink->sort_order + 1,
                'status' => 'active',
            ]);
            $this->command?->info('  dibuat: link footer Kebijakan Cookie');
        }
    }
}

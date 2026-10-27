<?php

use App\Models\WebPage;
use Database\Seeders\WebPageContentSeeder;

it('isi halaman hanya diganti kalau belum pernah diedit; halaman yang sudah diedit superadmin tidak tertimpa', function () {
    $edited = WebPage::create(['title' => 'Program Referral', 'slug' => 'ketentuan-referral', 'type' => 'document', 'content' => 'Sudah diedit superadmin', 'status' => 'active']);

    $this->seed(WebPageContentSeeder::class);

    expect($edited->fresh()->content)->toBe('Sudah diedit superadmin');
    foreach (['kebijakan-privasi', 'kebijakan-penggunaan', 'ketentuan-saldo', 'kebijakan-refund', 'ketentuan-referral', 'pemrosesan-data', 'affiliate'] as $slug) {
        expect(file_get_contents(database_path("seeders/content/pages/{$slug}.md")))->toContain('## 1.');
    }
});

it('Kebijakan Cookie dibuat sekali beserta link footer, mengikuti grup Kebijakan Privasi', function () {
    WebPage::create(['title' => 'Kebijakan Privasi', 'slug' => 'kebijakan-privasi', 'type' => 'document', 'content' => 'x', 'status' => 'active', 'show_in_footer' => true, 'footer_group' => 'Legal', 'footer_order' => 2]);
    \App\Models\WebFooter::create(['name' => 'Kebijakan Privasi', 'link' => '/page/kebijakan-privasi', 'group_name' => 'Legal', 'sort_order' => 5, 'status' => 'active']);

    $this->seed(WebPageContentSeeder::class);
    $this->seed(WebPageContentSeeder::class);

    $page = WebPage::where('slug', 'kebijakan-cookie')->sole();
    expect($page->footer_group)->toBe('Legal')->and($page->content)->toContain('## 3. Cookie Analitik')
        ->and(\App\Models\WebFooter::where('link', '/page/kebijakan-cookie')->sole()->group_name)->toBe('Legal');
});

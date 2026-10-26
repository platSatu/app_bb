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

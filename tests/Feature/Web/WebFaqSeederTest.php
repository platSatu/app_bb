<?php

use App\Models\WebFaq;
use Database\Seeders\WebFaqSeeder;

it('FAQ dibuat sekali, jawaban teks biasa, dan FAQ yang sudah diedit tidak ditimpa', function () {
    $edited = WebFaq::create(['name' => 'Apa itu bizbos?', 'descriptions' => 'Diedit superadmin', 'status' => 'active', 'sort_order' => 1]);

    $this->seed(WebFaqSeeder::class);
    $count = WebFaq::count();
    $this->seed(WebFaqSeeder::class);

    expect(WebFaq::count())->toBe($count)->toBeGreaterThan(20)
        ->and($edited->fresh()->descriptions)->toBe('Diedit superadmin')
        ->and(WebFaq::pluck('descriptions')->filter(fn ($a) => $a !== strip_tags($a)))->toBeEmpty();
});

it('jawaban versi lama yang menyebut angka diganti versi umum, kecuali sudah diedit', function () {
    $old = 'Paket tersedia untuk 6 bulan dan 12 bulan, mulai dari Pages 6 bulan Rp429.000 (sekitar Rp71 ribuan per bulan). Paket 12 bulan lebih hemat sekitar 20% dan ditandai Populer. Harga lengkap tiap paket bisa dilihat di menu Beli Paket setelah Anda masuk.';
    $price = WebFaq::create(['name' => 'Berapa harga paket bizbos?', 'descriptions' => $old, 'status' => 'active', 'sort_order' => 1]);
    $custom = WebFaq::create(['name' => 'Bisa coba gratis dulu?', 'descriptions' => 'Trial 14 hari (diedit)', 'status' => 'active', 'sort_order' => 2]);

    $this->seed(WebFaqSeeder::class);

    expect($price->fresh()->descriptions)->not->toContain('Rp')->toContain('menu Beli Paket')
        ->and($custom->fresh()->descriptions)->toBe('Trial 14 hari (diedit)');
});

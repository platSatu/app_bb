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

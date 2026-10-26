<?php

use App\Models\WebFeature;
use Database\Seeders\WebFeatureSeeder;
use Illuminate\Support\Facades\File;

it('seeder fitur website: 12 fitur berurutan dengan thumbnail PNG, aman dijalankan ulang tanpa menimpa hasil edit', function () {
    $this->seed(WebFeatureSeeder::class);

    $features = WebFeature::orderBy('sort_order')->get();
    expect($features)->toHaveCount(12)
        ->and($features->first()->name)->toBe('Semua Bisnis dalam Satu Dashboard')
        ->and($features->pluck('sort_order')->all())->toBe(range(1, 12))
        ->and($features->every(fn ($f) => File::exists(public_path('web/images/'.$f->images))))->toBeTrue();

    $features->first()->update(['description' => 'Diedit superadmin']);
    $this->seed(WebFeatureSeeder::class);

    expect(WebFeature::count())->toBe(12)
        ->and($features->first()->fresh()->description)->toBe('Diedit superadmin');
});

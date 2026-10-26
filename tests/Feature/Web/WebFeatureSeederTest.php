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

it('fitur lama diberi gambar PNG baru sekali saja, dan absensi lewat WhatsApp disembunyikan', function () {
    $form = WebFeature::create(['name' => 'Kelola Form Pendaftaran', 'description' => 'lama', 'images' => 'features/lama.jpg', 'status' => 'active', 'sort_order' => 1]);
    $reply = WebFeature::create(['name' => 'Auto Reply', 'description' => 'lama', 'images' => 'features/lama2.jpg', 'status' => 'active', 'sort_order' => 2]);
    $absen = WebFeature::create(['name' => 'Absensi lewat WhatsApp', 'description' => 'lama', 'status' => 'active', 'sort_order' => 3]);

    $this->seed(WebFeatureSeeder::class);

    expect($form->fresh()->images)->toBe('features/bizbos-form.png')
        ->and($form->fresh()->description)->toContain('kuis')
        ->and($reply->fresh()->images)->toBe('features/bizbos-autoreply.png')
        ->and($absen->fresh()->status)->toBe('inactive')
        ->and(WebFeature::where('name', 'Chatbot & Auto Reply')->value('images'))->toBe('features/bizbos-chatbot.png');

    $form->fresh()->update(['description' => 'Diedit superadmin']);
    $this->seed(WebFeatureSeeder::class);
    expect($form->fresh()->description)->toBe('Diedit superadmin');
});

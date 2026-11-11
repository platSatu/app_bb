<?php

use App\Models\WebFeature;
use App\Support\Web\SortOrder;
use Database\Seeders\WebFeatureSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\File;

/**
 * Dua fitur unggulan baru di beranda website: Absensi Scan QR & Placement Test (teks di WebFeatureSeeder::FEATURES,
 * gambar PNG transparan di database/seeders/assets/features). Hanya menambah; fitur yang sudah ada tidak disentuh,
 * dan kalau namanya sudah ada (dibuat manual), dilewati. Setelah deploy: php artisan cache:clear di website.
 */
return new class extends Migration
{
    private const NEW = ['attendance', 'placement'];

    public function up(): void
    {
        foreach (self::NEW as $key) {
            [$name, $description] = WebFeatureSeeder::FEATURES[$key];

            if (WebFeature::where('name', $name)->exists()) {
                continue;
            }

            File::ensureDirectoryExists(public_path('web/images/features'));
            File::copy(database_path("seeders/assets/features/{$key}.png"), public_path("web/images/features/bizbos-{$key}.png"));

            $feature = new WebFeature();
            $feature->forceFill([
                'name' => $name,
                'description' => $description,
                'images' => "features/bizbos-{$key}.png",
                'status' => 'active',
                'sort_order' => SortOrder::next(WebFeature::query()),
            ])->save();
        }
    }

    public function down(): void
    {
        WebFeature::whereIn('name', array_map(fn ($key) => WebFeatureSeeder::FEATURES[$key][0], self::NEW))->delete();
    }
};

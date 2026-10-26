<?php

use App\Models\CategoryApplication;
use App\Models\Package;
use App\Models\User;
use Database\Seeders\PackageSeeder;

it('seeder paket: 5 kombinasi x 4 durasi, 12 bulan Populer, aman dijalankan ulang tanpa menimpa hasil edit; katalog per tab kategori', function () {
    $this->seed(PackageSeeder::class);

    expect(Package::count())->toBe(20)
        ->and(CategoryApplication::count())->toBe(5)
        ->and(Package::where('is_trial', true)->where('price', 0)->where('duration', 7)->count())->toBe(5)
        ->and(Package::where('is_featured', true)->pluck('duration')->unique()->all())->toBe([365]);

    $complete = Package::where('name', 'Komplit - 1 Bulan')->firstOrFail();
    expect($complete->apps)->toBe(['pages', 'schedules', 'invoices', 'whatsapp'])
        ->and($complete->limits)->toBe(['invoices' => ['monthly' => 2000], 'whatsapp' => ['monthly' => 20000]])
        ->and($complete->features())->toHaveCount(5);

    $complete->update(['price' => 299000]);
    $this->seed(PackageSeeder::class);

    expect(Package::count())->toBe(20)->and($complete->fresh()->price)->toBe(299000);

    $this->actingAs(User::factory()->create())->get(route('packages.catalog'))->assertOk()
        // Tab = kategori, urut dari harga termurah; isi tab Pages: Trial, 1, 6, 12 bulan.
        ->assertSeeInOrder(['Pages', 'Invoice', 'Invoice + WhatsApp', 'Pages + Invoice + WhatsApp', 'Komplit', 'Pages - Trial 7 Hari', 'Pages - 1 Bulan', 'Pages - 6 Bulan', 'Pages - 12 Bulan'])
        ->assertSee('Populer');
});

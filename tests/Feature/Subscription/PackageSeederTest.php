<?php

use App\Models\CategoryApplication;
use App\Models\Package;
use App\Models\User;
use Database\Seeders\PackageSeeder;

it('seeder paket: 5 kategori x 3 durasi (trial, 6, 12 bulan), 12 bulan Populer, aman dijalankan ulang tanpa menimpa hasil edit; katalog per tab kategori', function () {
    // Sisa seeder versi lama (paket 1 bulan) ikut dibersihkan.
    Package::create(['category_application_id' => CategoryApplication::create(['name' => 'Pages', 'status' => 'active'])->id,
        'name' => 'Pages - 1 Bulan', 'price' => 79000, 'duration' => 30, 'apps' => ['pages'], 'status' => 'active']);
    $this->seed(PackageSeeder::class);

    expect(Package::count())->toBe(15)
        ->and(CategoryApplication::count())->toBe(5)
        ->and(Package::where('is_trial', true)->where('price', 0)->where('duration', 7)->count())->toBe(5)
        ->and(Package::where('is_featured', true)->pluck('duration')->unique()->all())->toBe([365]);

    $complete = Package::where('name', 'Komplit - 6 Bulan')->firstOrFail();
    expect($complete->apps)->toBe(['events', 'pages', 'schedules', 'invoices', 'whatsapp'])
        ->and($complete->limits)->toBe(['invoices' => ['monthly' => 2000], 'whatsapp' => ['monthly' => 20000]])
        ->and($complete->features())->toHaveCount(35)
        ->and(array_slice($complete->features(), 0, 5))->toContain('Data customer & export Excel')
        ->and($complete->features())->toContain('AI bot dengan pengetahuan bisnis Anda', 'Portal orang tua/murid & pengajar');

    // Paket dari seeder versi lama (semua di satu kategori) dipindah ke kategorinya; harga hasil edit tetap.
    $old = CategoryApplication::create(['name' => 'Whatsapp', 'status' => 'active']);
    $complete->update(['price' => 299000, 'category_application_id' => $old->id]);
    $this->seed(PackageSeeder::class);

    expect(Package::count())->toBe(15)
        ->and($complete->fresh()->price)->toBe(299000)
        ->and($complete->fresh()->category->name)->toBe('Komplit');

    $this->actingAs(User::factory()->create())->get(route('packages.catalog'))->assertOk()
        // Tab = kategori, urut dari harga termurah; isi tab Pages: Trial, 6, 12 bulan.
        ->assertSeeInOrder(['Pages', 'Invoice', 'Invoice + WhatsApp', 'Pages + Invoice + WhatsApp', 'Komplit', 'Pages - Trial 7 Hari', 'Pages - 6 Bulan', 'Pages - 12 Bulan'])
        ->assertSee('Populer');
});

it('paket lama yang deskripsinya masih ringkasan dilengkapi rinciannya; yang sudah diedit tidak disentuh', function () {
    $this->seed(PackageSeeder::class);
    $pages = Package::where('name', 'Pages - 6 Bulan')->firstOrFail();
    $invoice = Package::where('name', 'Invoice - 6 Bulan')->firstOrFail();
    $pages->update(['description' => "Pages: landing page, bio link, katalog online, tiket event & kuis\nData customer & export Excel"]);
    $invoice->update(['description' => 'Diedit superadmin']);

    $this->seed(PackageSeeder::class);

    expect($pages->fresh()->features())->toContain('Kuis & tes online dengan skor otomatis')->toHaveCount(9)
        ->and($invoice->fresh()->description)->toBe('Diedit superadmin');
});

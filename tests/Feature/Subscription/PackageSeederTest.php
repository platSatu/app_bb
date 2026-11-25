<?php

use App\Models\CategoryApplication;
use App\Models\Package;
use App\Models\SubscriptionHistory;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Subscription\PlanLimits;
use App\Services\Subscription\SubscriptionService;
use Database\Seeders\PackageSeeder;
use Illuminate\Support\Str;

it('seeder paket: Kursus per jumlah murid (bulanan, 6 & 12 bulan, trial 30 hari), Invoice/WhatsApp tetap, Pages & Event gratis; katalog per tab', function () {
    $this->seed(PackageSeeder::class);

    // Kursus Starter: Trial + 3 durasi; Growth/Pro/Unlimited: 3 durasi; Invoice, WhatsApp, Invoice + WhatsApp: Trial + 2 durasi; Pages & Event: 1.
    expect(Package::count())->toBe(24)
        ->and(CategoryApplication::where('status', 'active')->count())->toBe(9)
        ->and(Package::where('is_trial', true)->where('price', 0)->count())->toBe(4)
        ->and(Package::where('is_featured', true)->pluck('duration')->unique()->values()->all())->toBe([365]);

    $price = fn (string $name) => Package::where('name', $name)->firstOrFail()->price;
    expect($price('Kursus Starter - Bulanan'))->toBe(99000)
        ->and($price('Kursus Starter - 6 Bulan'))->toBe(535000)
        ->and($price('Kursus Starter - 12 Bulan'))->toBe(990000)
        ->and($price('Kursus Growth - Bulanan'))->toBe(199000)
        ->and($price('Kursus Pro - Bulanan'))->toBe(349000)
        ->and($price('Kursus Unlimited - 12 Bulan'))->toBe(5990000)
        ->and($price('Invoice - 12 Bulan'))->toBe(949000)
        ->and($price('WhatsApp - 6 Bulan'))->toBe(654000);

    $starter = Package::where('name', 'Kursus Starter - Bulanan')->firstOrFail();
    expect($starter->duration)->toBe(30)
        ->and($starter->apps)->toBe(['events', 'pages', 'schedules', 'invoices', 'whatsapp'])
        ->and($starter->limits)->toBe(['schedules' => ['students' => 50], 'invoices' => ['monthly' => 100], 'whatsapp' => ['monthly' => 1500]])
        ->and($starter->features())->toContain(
            'Jadwal les & kursus: kelas bulanan atau paket pertemuan (sampai 50 murid aktif)',
            'Invoice: tagihan online dengan link bayar VA, QRIS & e-wallet (100 invoice/bulan)',
            'WhatsApp: inbox tim CS, broadcast, auto reply & chatbot (1.500 pesan/bulan)',
            'Pengingat les H-1 lewat WhatsApp',
        );

    $trial = Package::where('name', 'Kursus Starter - Trial 30 Hari')->firstOrFail();
    expect($trial->is_trial)->toBeTrue()->and($trial->duration)->toBe(30)
        ->and($trial->limits)->toBe(['schedules' => ['students' => 30], 'invoices' => ['monthly' => 60], 'whatsapp' => ['monthly' => 300]])
        ->and($trial->features()[0])->toBe('Coba gratis 30 hari');

    // Unlimited: tanpa batas murid. Growth/Pro/Unlimited tanpa trial (trial dipakai sekali per akun).
    $unlimited = Package::where('name', 'Kursus Unlimited - Bulanan')->firstOrFail();
    expect($unlimited->limits)->toBe(['invoices' => ['monthly' => 2000], 'whatsapp' => ['monthly' => 20000]])
        ->and($unlimited->features())->toContain('Murid aktif tanpa batas')
        ->and(Package::where('name', 'like', 'Kursus Growth - Trial%')->exists())->toBeFalse();

    // Invoice tetap bisa dibeli sendiri.
    expect(Package::where('name', 'Invoice - 6 Bulan')->firstOrFail()->apps)->toBe(['invoices'])
        ->and(Package::where('name', 'Pages - Gratis Selamanya')->firstOrFail()->price)->toBe(0);

    $this->actingAs(User::factory()->create())->get(route('packages.catalog'))->assertOk()
        // Tab urut dari harga termurah; isi tab Kursus Starter: Trial, Bulanan, 6, 12 bulan.
        ->assertSeeInOrder(['Kursus Starter', 'Kursus Growth', 'Kursus Pro', 'Kursus Starter - Trial 30 Hari', 'Kursus Starter - Bulanan', 'Kursus Starter - 6 Bulan', 'Kursus Starter - 12 Bulan'])
        ->assertSee('Hemat 17%')
        ->assertSee('TERPOPULER');
});

it('paket Komplit tidak dijual lagi: yang belum pernah dibeli dihapus, yang sudah dibeli dinonaktifkan', function () {
    $category = CategoryApplication::create(['name' => 'Komplit', 'status' => 'active']);
    $old = fn (string $label, int $days) => Package::create(['category_application_id' => $category->id, 'name' => "Komplit - {$label}", 'price' => 2694000,
        'duration' => $days, 'apps' => ['events', 'pages', 'schedules', 'invoices', 'whatsapp'], 'status' => 'active']);
    $unsold = $old('6 Bulan', 180);
    $sold = $old('12 Bulan', 365);
    (new Voucher())->forceFill(['package_id' => $sold->id, 'user_id' => User::factory()->create()->id, 'code' => Str::upper(Str::random(10)),
        'price' => 4788000, 'valid_from' => now(), 'valid_until' => now()->addDays(30)])->save();

    $this->seed(PackageSeeder::class);

    expect(Package::find($unsold->id))->toBeNull()
        ->and($sold->fresh()->status)->toBe(Package::STATUS_INACTIVE)
        ->and($category->fresh()->status)->toBe('inactive');
});

it('aman dijalankan ulang: harga & isi yang sudah diedit superadmin tidak ditimpa', function () {
    $this->seed(PackageSeeder::class);
    $growth = Package::where('name', 'Kursus Growth - Bulanan')->firstOrFail();
    $growth->update(['price' => 179000, 'description' => 'Diedit superadmin']);

    $this->seed(PackageSeeder::class);

    expect(Package::count())->toBe(24)
        ->and($growth->fresh()->price)->toBe(179000)
        ->and($growth->fresh()->description)->toBe('Diedit superadmin');
});

it('membeli Kursus Starter memberi batas 50 murid aktif ke branch', function () {
    $this->seed(PackageSeeder::class);
    $branch = appBranch([]);
    $starter = Package::where('name', 'Kursus Starter - Bulanan')->firstOrFail();

    app(SubscriptionService::class)->extend($branch, 'schedules', 30, SubscriptionHistory::SOURCE_MANUAL, null, $starter->id);

    expect(app(PlanLimits::class)->limit($branch->id, 'schedules', 'students'))->toBe(50);
});

<?php

use App\Models\BranchSubscription;
use App\Models\CategoryApplication;
use App\Models\Customer;
use App\Models\Package;
use App\Models\PageCategory;
use App\Models\PageType;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\TicketOrder;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserCompany;
use App\Services\Customer\CustomerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Event = page bertemplate tiket, dijual sebagai layanan sendiri. Fiturnya sama, hanya menu & paketnya terpisah. */
/** Helper sendiri (bukan appBranch/customerIn milik test lain) supaya file ini bisa dijalankan terpisah. */
function eventBranch(array $apps): UserBranch
{
    $owner = User::factory()->create();
    $company = tap((new UserCompany())->forceFill(['user_id' => $owner->id, 'name' => 'EO Uji', 'slug' => 'eo-'.Str::lower(Str::random(6))]))->save();
    $branch = tap((new UserBranch())->forceFill(['user_id' => $owner->id, 'company_id' => $company->id, 'name' => 'Pusat', 'status' => 'active']))->save();
    foreach ($apps as $app) {
        (new BranchSubscription())->forceFill(['branch_id' => $branch->id, 'app_key' => $app, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()])->save();
    }

    return $branch;
}

function eventCustomerIn(UserBranch $branch, string $app, string $name, string $phone): Customer
{
    return DB::transaction(fn () => app(CustomerService::class)->resolve($branch, $app, $name, $phone, null, 'tes'));
}

function eventTypes(): array
{
    return [
        PageType::create(['name' => 'Tiket', 'template' => PageType::TEMPLATE_TICKET, 'status' => 'active']),
        PageType::create(['name' => 'Landing', 'template' => PageType::TEMPLATE_LANDING, 'status' => 'active']),
    ];
}

function eventPage(UserBranch $branch, PageType $type, string $name): PageCategory
{
    return tap((new PageCategory())->forceFill([
        'branch_id' => $branch->id, 'page_type_id' => $type->id, 'name' => $name,
        'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)), 'status' => 'active',
    ]))->save();
}

beforeEach(fn () => Storage::fake('public'));

it('menu Event dan Pages terpisah: masing-masing hanya menampilkan dan membuka miliknya sendiri', function () {
    [$ticket, $landing] = eventTypes();
    $branch = eventBranch(['events', 'pages']);
    $event = eventPage($branch, $ticket, 'Konser Amal');
    $page = eventPage($branch, $landing, 'Profil Usaha');
    $this->actingAs(User::find($branch->user_id));

    $this->get(route('events.index'))->assertOk()->assertSee('Konser Amal')->assertDontSee('Profil Usaha')->assertSee('Buat Event');
    $this->get(route('pages.index'))->assertOk()->assertSee('Profil Usaha')->assertDontSee('Konser Amal');

    // Tab tiket hanya lewat /events, page lain hanya lewat /pages.
    $this->get(route('events.ticket.categories.index', $event))->assertOk();
    $this->get(route('events.edit', $event))->assertOk()->assertSee('Informasi Event');
    $this->get(route('pages.edit', $event))->assertNotFound();
    $this->get(route('events.edit', $page))->assertNotFound();
    $this->get(route('events.builder', $page))->assertNotFound();
});

it('membuat event: kategori otomatis tiket (tanpa pilihan), lewat simulasi dulu, lalu lanjut isi kategori tiket', function () {
    [$ticket] = eventTypes();
    $branch = eventBranch(['events']);
    $this->actingAs(User::find($branch->user_id));

    $this->get(route('events.create'))->assertRedirect(route('events.ticket-fee'));
    $this->get(route('events.create', ['type' => $ticket->id, 'fee_payer' => 'organizer']))->assertOk()
        ->assertDontSee('id="page_type_id"', false)
        ->assertSee('name="page_type_id" value="'.$ticket->id.'"', false);

    $response = $this->post(route('events.store'), [
        'page_type_id' => $ticket->id, 'branch_id' => $branch->id, 'name' => 'Seminar Bisnis', 'status' => 'active', 'ticket_fee_payer' => 'organizer',
    ]);

    $event = PageCategory::where('name', 'Seminar Bisnis')->firstOrFail();
    $response->assertRedirect(route('events.ticket.categories.index', $event));
    expect($event->page_type_id)->toBe($ticket->id);

    // Kategori non-tiket tidak bisa dipaksa lewat menu Event, dan sebaliknya.
    $landing = PageType::where('template', PageType::TEMPLATE_LANDING)->first();
    $this->post(route('events.store'), ['page_type_id' => $landing->id, 'branch_id' => $branch->id, 'name' => 'Salah', 'status' => 'active'])
        ->assertSessionHasErrors('page_type_id');
});

it('paket Event dan Pages berdiri sendiri: menu, form, dan halaman publik ikut paket masing-masing', function () {
    [$ticket, $landing] = eventTypes();
    $branch = eventBranch(['pages']);
    $owner = User::find($branch->user_id);
    $event = eventPage($branch, $ticket, 'Konser Lama');
    $page = eventPage($branch, $landing, 'Profil');

    // Hanya punya Pages: menu Event diarahkan ke Paket Saya, event tidak bisa dibuka, halaman publiknya tutup.
    $this->actingAs($owner)->get(route('events.index'))->assertRedirect(route('profile.packages'))
        ->assertSessionHas('warning', fn ($m) => str_contains($m, 'Layanan Event belum aktif'));
    $this->get(route('events.edit', $event))->assertRedirect(route('profile.packages'));
    $this->get(route('pages.index'))->assertOk()->assertDontSee('Konser Lama');
    $this->get(route('page.show', $event->slug))->assertStatus(503);
    $this->get(route('page.show', $page->slug))->assertOk();

    // Setelah Event aktif, event langsung terbuka lagi.
    (new BranchSubscription())->forceFill(['branch_id' => $branch->id, 'app_key' => 'events', 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()])->save();
    app()->forgetScopedInstances(); // AppAccess menyimpan hasil cek per request; di test request berikutnya memakai app yang sama.
    $this->get(route('events.index'))->assertOk()->assertSee('Konser Lama');
    $this->get(route('page.show', $event->slug))->assertOk();
});

it('superadmin: Applications › Event dan › Pages terpisah, form paket punya batas Event sendiri', function () {
    [$ticket, $landing] = eventTypes();
    $branch = eventBranch(['events', 'pages']);
    eventPage($branch, $ticket, 'Konser Amal');
    eventPage($branch, $landing, 'Profil Usaha');
    $admin = User::factory()->create(['user_type' => User::TYPE_SUPERADMIN]);

    $this->actingAs($admin)->get(route('superadmin.events.index'))->assertOk()->assertSee('Konser Amal')->assertDontSee('Profil Usaha')->assertSee('Biaya Layanan Tiket');
    $this->get(route('superadmin.pages.index'))->assertOk()->assertSee('Profil Usaha')->assertDontSee('Konser Amal')->assertDontSee('Biaya Layanan Tiket');

    $category = CategoryApplication::create(['name' => 'Event', 'status' => 'active']);
    $this->get(route('superadmin.packages.create'))->assertOk()->assertSee('value="events"', false)->assertSee('limits[events]['.$ticket->id.']', false);
    $this->post(route('superadmin.packages.store'), [
        'category_application_id' => $category->id, 'name' => 'Event - 6 Bulan', 'price' => 100000, 'duration' => 180, 'status' => 'active',
        'apps' => ['events'],
        // Kategori tiket di kolom Pages (atau sebaliknya) diabaikan.
        'limits' => ['events' => [$ticket->id => 3, $landing->id => 9], 'pages' => [$ticket->id => 1]],
    ])->assertSessionHas('success');

    $package = Package::where('name', 'Event - 6 Bulan')->firstOrFail();
    expect($package->apps)->toBe(['events'])
        ->and($package->limits['events'])->toBe([$ticket->id => 3])
        ->and($package->limits['pages'])->toBe([]);
});

it('pembeli tiket masuk customers Event, bukan Pages', function () {
    [$ticket] = eventTypes();
    $branch = eventBranch(['events', 'pages']);
    $this->actingAs(User::find($branch->user_id));

    eventCustomerIn($branch, 'events', 'Pembeli Tiket', '081111111111');
    eventCustomerIn($branch, 'pages', 'Pemesan Katalog', '082222222222');

    $this->get(route('events.customers.index'))->assertOk()->assertSee('Pembeli Tiket')->assertDontSee('Pemesan Katalog');
    $this->get(route('pages.customers.index'))->assertOk()->assertSee('Pemesan Katalog')->assertDontSee('Pembeli Tiket');
});

it('migrasi: langganan & paket Pages ikut membuka Event, batas tiket pindah, pembeli tiket pindah, izin staff ikut', function () {
    [$ticket, $landing] = eventTypes();
    $branch = eventBranch([]);
    $ends = now()->addDays(40)->startOfSecond();
    (new BranchSubscription())->forceFill(['branch_id' => $branch->id, 'app_key' => 'pages', 'starts_at' => now()->subDay(), 'ends_at' => $ends,
        'limits' => [$ticket->id => 2, $landing->id => 5]])->save();

    $package = Package::create(['category_application_id' => CategoryApplication::create(['name' => 'Pages', 'status' => 'active'])->id,
        'name' => 'Pages - 6 Bulan', 'price' => 1, 'duration' => 180, 'apps' => ['pages', 'invoices'], 'status' => 'active',
        'limits' => ['pages' => [$ticket->id => 2, $landing->id => 5], 'invoices' => ['monthly' => 10]]]);

    $event = eventPage($branch, $ticket, 'Konser');
    $buyer = eventCustomerIn($branch, 'pages', 'Pembeli Saja', '081111111111');
    $both = eventCustomerIn($branch, 'pages', 'Pembeli & Pemesan', '082222222222');
    $catalogOnly = eventCustomerIn($branch, 'pages', 'Pemesan Saja', '083333333333');
    $order = fn (Customer $c) => DB::table('ticket_orders')->insert([
        'id' => (string) Str::orderedUuid(), 'page_id' => $event->id, 'company_id' => $branch->company_id, 'customer_id' => $c->id,
        'reference' => 'T'.Str::upper(Str::random(10)), 'access_token' => Str::random(48), 'name' => $c->name, 'email' => 'x@mail.test', 'phone' => $c->phone,
        'status' => TicketOrder::STATUS_PAID, 'total' => 0, 'expires_at' => now()->addHour(), 'created_at' => now(), 'updated_at' => now(),
    ]);
    $order($buyer);
    $order($both);
    $catalogOrder = fn (Customer $c) => DB::table('catalog_orders')->insert([
        'id' => (string) Str::orderedUuid(), 'page_id' => eventPage($branch, $landing, 'Toko')->id, 'company_id' => $branch->company_id, 'customer_id' => $c->id,
        'reference' => 'C'.Str::upper(Str::random(10)), 'access_token' => Str::random(48), 'name' => $c->name, 'phone' => $c->phone,
        'status' => 'pending', 'total' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $catalogOrder($both);
    $catalogOrder($catalogOnly);

    $role = tap((new Role())->forceFill(['name' => 'Tim Konten', 'scope' => Role::SCOPE_PLATFORM]))->save();
    tap((new RolePermission())->forceFill(['role_id' => $role->id, 'permission_key' => 'superadmin.pages', 'ability' => 'manage']))->save();

    $migration = require database_path('migrations/2026_11_19_100000_split_events_app_from_pages.php');
    $migration->up();
    $migration->up(); // aman dijalankan ulang

    $events = BranchSubscription::where('branch_id', $branch->id)->where('app_key', 'events')->firstOrFail();
    $pages = BranchSubscription::where('branch_id', $branch->id)->where('app_key', 'pages')->firstOrFail();
    expect($events->ends_at->equalTo($ends))->toBeTrue()
        ->and($events->limits)->toBe([$ticket->id => 2])
        ->and($pages->limits)->toBe([$landing->id => 5]);

    $package->refresh();
    expect($package->apps)->toBe(['events', 'pages', 'invoices'])
        ->and($package->limits['events'])->toBe([$ticket->id => 2])
        ->and($package->limits['pages'])->toBe([$landing->id => 5])
        ->and($package->limits['invoices'])->toBe(['monthly' => 10]);

    expect($buyer->fresh()->app_key)->toBe('events')
        ->and($catalogOnly->fresh()->app_key)->toBe('pages')
        ->and($both->fresh()->app_key)->toBe('pages')
        ->and(Customer::where('branch_id', $branch->id)->where('app_key', 'events')->where('phone', $both->phone)->count())->toBe(1);

    $copy = Customer::where('branch_id', $branch->id)->where('app_key', 'events')->where('phone', $both->phone)->first();
    expect(DB::table('ticket_orders')->where('customer_id', $copy->id)->count())->toBe(1)
        ->and(DB::table('catalog_orders')->where('customer_id', $both->id)->count())->toBe(1);

    expect(RolePermission::where('role_id', $role->id)->where('permission_key', 'superadmin.events')->value('ability'))->toBe('manage');
});

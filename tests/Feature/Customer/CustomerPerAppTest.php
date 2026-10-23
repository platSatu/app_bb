<?php

use App\Models\BranchSubscription;
use App\Models\ContactGroup;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserCompany;
use App\Models\WaBroadcast;
use App\Services\Customer\CustomerService;
use App\Services\WhatsApp\BroadcastService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function appBranch(array $apps = ['pages', 'invoices', 'whatsapp']): UserBranch
{
    $owner = User::factory()->create();
    $company = tap((new UserCompany())->forceFill(['user_id' => $owner->id, 'name' => 'PT Uji', 'slug' => 'uji-'.Str::lower(Str::random(5))]))->save();
    $branch = tap((new UserBranch())->forceFill(['user_id' => $owner->id, 'company_id' => $company->id, 'name' => 'Pusat', 'status' => 'active']))->save();
    foreach ($apps as $app) {
        (new BranchSubscription())->forceFill(['branch_id' => $branch->id, 'app_key' => $app, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()])->save();
    }

    return $branch;
}

function customerIn(UserBranch $branch, string $app, string $name, string $phone, ?string $email = null): Customer
{
    return DB::transaction(fn () => app(CustomerService::class)->resolve($branch, $app, $name, $phone, $email, 'tes'));
}

it('orang yang sama di dua aplikasi menjadi dua customer terpisah', function () {
    $branch = appBranch();
    $pages = customerIn($branch, 'pages', 'Budi', '081234567890', 'budi@lama.test');
    $invoice = customerIn($branch, 'invoices', 'Budi', '081234567890', 'budi@baru.test');

    expect($pages->id)->not->toBe($invoice->id)
        ->and($pages->app_key)->toBe('pages')->and($invoice->app_key)->toBe('invoices')
        ->and($invoice->email)->toBe('budi@baru.test');

    // Di aplikasi yang sama tetap satu baris (dicocokkan lewat nomor).
    expect(customerIn($branch, 'pages', 'Budi S', '081234567890')->id)->toBe($pages->id);
    expect(Customer::count())->toBe(2);
});

it('setiap menu Customers hanya menampilkan customer aplikasinya', function () {
    $branch = appBranch();
    customerIn($branch, 'pages', 'Pembeli Tiket', '081111111111');
    customerIn($branch, 'invoices', 'Wali Murid', '082222222222');
    customerIn($branch, 'whatsapp', 'Teman Chat', '083333333333');
    $this->actingAs($branch->user);

    $this->get(route('pages.customers.index'))->assertOk()->assertSee('Pembeli Tiket')->assertDontSee('Wali Murid')->assertDontSee('Teman Chat');
    $this->get(route('invoices.customers.index'))->assertOk()->assertSee('Wali Murid')->assertDontSee('Pembeli Tiket');
    $this->get(route('whatsapp.contacts.index', ['branch' => $branch->id]))->assertOk()->assertSee('Teman Chat')->assertDontSee('Wali Murid');

    // Detail customer aplikasi lain lewat URL menu ini ditolak.
    $this->get(route('pages.customers.show', Customer::forApp('invoices')->first()))->assertNotFound();
});

it('Customer Baru di kategori Invoice tidak memakai data customer Pages', function () {
    $branch = appBranch();
    customerIn($branch, 'pages', 'Natan', '081234567890', 'ensosmed@mail.test');
    $category = tap(new \App\Models\InvoiceCategory(['name' => 'Iuran', 'prefix' => 'IUR', 'late_fee_mode' => 'none', 'status' => 'active']), fn ($c) => $c->forceFill(['branch_id' => $branch->id])->save());

    $this->actingAs($branch->user)->post(route('invoices.categories.quick-add', $category), ['name' => 'Dev', 'phone' => '081234567890', 'email' => 'devkonexa@mail.test'])
        ->assertSessionHas('success');

    expect(Customer::forApp('invoices')->first()->email)->toBe('devkonexa@mail.test')
        ->and(Customer::forApp('pages')->first()->email)->toBe('ensosmed@mail.test');
});

it('blast WhatsApp bisa ke semua customer aplikasi lain dan group, tanpa nomor dobel', function () {
    $branch = appBranch();
    customerIn($branch, 'pages', 'A', '081111111111');
    customerIn($branch, 'pages', 'B', '082222222222');
    customerIn($branch, 'invoices', 'C', '083333333333');
    $wa = customerIn($branch, 'whatsapp', 'A di WA', '081111111111');
    $group = tap(new ContactGroup(['name' => 'VIP']), fn ($g) => $g->forceFill(['branch_id' => $branch->id])->save());
    $group->customers()->attach($wa->id, ['created_at' => now()]);

    $broadcast = (new WaBroadcast())->forceFill(['branch_id' => $branch->id, 'type' => 'blast', 'target' => ['group_ids' => [$group->id], 'apps' => ['pages']]]);

    expect(app(BroadcastService::class)->recipients($broadcast)->pluck('phone')->sort()->values()->all())
        ->toBe(['6281111111111', '6282222222222']);
});

it('migrasi memecah customer lama sesuai pemakaiannya', function () {
    $branch = appBranch();
    $old = customerIn($branch, 'whatsapp', 'Budi', '081234567890', 'budi@mail.test');
    $invoice = tap(new Invoice(), fn ($i) => $i->forceFill([
        'branch_id' => $branch->id, 'company_id' => $branch->company_id, 'customer_id' => $old->id, 'source' => 'dashboard', 'number' => 'INV-1',
        'token' => Str::random(48), 'description' => 'SPP', 'customer_name' => 'Budi', 'amount' => 1000, 'status' => 'unpaid',
    ])->save());
    $group = tap(new ContactGroup(['name' => 'Ortu']), fn ($g) => $g->forceFill(['branch_id' => $branch->id])->save());
    $group->customers()->attach($old->id, ['created_at' => now()]);

    $migration = require database_path('migrations/2026_10_31_100000_add_app_key_to_customers.php');
    (fn () => $this->splitExisting())->call($migration);

    $invoiceCustomer = Customer::forApp('invoices')->sole();
    $waCustomer = Customer::forApp('whatsapp')->sole();
    expect($invoiceCustomer->id)->toBe($old->id)
        ->and($invoice->fresh()->customer_id)->toBe($old->id)
        ->and($group->customers()->pluck('customers.id')->all())->toBe([$waCustomer->id])
        ->and($waCustomer->phone)->toBe($old->phone);
});

it('form broadcast menampilkan pilihan customer per aplikasi, menu List Page tidak ikut aktif di Customers', function () {
    $branch = appBranch();
    customerIn($branch, 'pages', 'Pembeli', '081111111111');
    customerIn($branch, 'whatsapp', 'Teman', '082222222222');
    (new \App\Models\WaDevice())->forceFill(['branch_id' => $branch->id, 'name' => 'CS', 'status' => 'connected'])->save();
    $this->actingAs($branch->user);

    $this->get(route('whatsapp.messages.create', ['branch' => $branch->id, 'type' => 'blast']))->assertOk()
        ->assertSee('Semua customer Pages')->assertSee('(1 nomor)', false);
    $this->get(route('whatsapp.messages.create', ['branch' => $branch->id]))->assertOk()
        ->assertSee('Customers Pages')->assertSee('Customers WhatsApp')->assertSee('Pembeli');

    $html = $this->get(route('pages.customers.index'))->getContent();
    expect(substr_count($html, 'pe-nav-link active'))->toBeGreaterThanOrEqual(1);
    expect(preg_match('/href="'.preg_quote(route('pages.index'), '/').'" class="pe-nav-link active"/', $html))->toBe(0);
});

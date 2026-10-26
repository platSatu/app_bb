<?php

use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->superadmin = User::factory()->create(['user_type' => User::TYPE_SUPERADMIN]);
});

it('menu Customer ada di Data User (bukan lagi Buku Kontak di Applications) dan tampil lengkap', function () {
    $branch = appBranch(['pages']);
    customerIn($branch, 'pages', 'Budi Santoso', '081234567890', 'budi@mail.test');

    $menu = collect(config('menu.superadmin.groups'));
    expect($menu['data-user']['items'])->toHaveKey('superadmin.customers')
        ->and($menu['applications']['submenus'])->not->toHaveKey('contacts');

    $this->actingAs($this->superadmin)->get(route('superadmin.contacts.index'))
        ->assertOk()->assertSee('Budi Santoso')->assertSee('Export Excel')->assertSee('Impor Excel')->assertSee('Sumber');
});

it('export Excel mengikuti filter yang dipakai', function () {
    $branch = appBranch(['pages', 'invoices']);
    customerIn($branch, 'pages', 'Budi Pages', '081234567890', 'budi@mail.test');
    customerIn($branch, 'invoices', 'Ani Invoice', '081298765432', 'ani@mail.test');

    $response = $this->actingAs($this->superadmin)->get(route('superadmin.contacts.export', ['app' => 'invoices']))->assertOk();

    $path = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($path, $response->streamedContent());
    $rows = IOFactory::load($path)->getActiveSheet()->toArray();
    unlink($path);

    expect($rows[0][0])->toBe('Nama')
        ->and(collect($rows)->pluck(0)->all())->toContain('Ani Invoice')->not->toContain('Budi Pages');
});

it('import Excel ke branch & aplikasi pilihan superadmin; branch yang paketnya tidak aktif ditolak', function () {
    $branch = appBranch(['pages']);
    $csv = UploadedFile::fake()->createWithContent('customers.csv', "nama,whatsapp,email\nCitra,081211112222,citra@mail.test\n");

    $this->actingAs($this->superadmin)
        ->post(route('superadmin.contacts.import'), ['app' => 'pages', 'branch_id' => $branch->id, 'file' => $csv])
        ->assertRedirect()->assertSessionHas('success');
    expect(Customer::where('branch_id', $branch->id)->where('app_key', 'pages')->where('name', 'Citra')->exists())->toBeTrue();

    // Paket Invoice branch ini tidak aktif: tidak bisa diimpor ke aplikasi Invoice.
    $csv = UploadedFile::fake()->createWithContent('customers.csv', "nama,whatsapp,email\nDodi,081233334444,dodi@mail.test\n");
    $this->post(route('superadmin.contacts.import'), ['app' => 'invoices', 'branch_id' => $branch->id, 'file' => $csv])
        ->assertSessionHasErrors('branch_id');
});

it('hapus banyak: yang dicentang saja, atau semua yang cocok dengan filter', function () {
    $branch = appBranch(['pages', 'invoices']);
    $a = customerIn($branch, 'pages', 'Ani', '081200000001');
    $b = customerIn($branch, 'pages', 'Budi', '081200000002');
    $c = customerIn($branch, 'invoices', 'Cici', '081200000003');
    $d = customerIn($branch, 'invoices', 'Dedi', '081200000004');
    $this->actingAs($this->superadmin);

    $this->get(route('superadmin.contacts.index'))->assertSee('Hapus yang dipilih');

    $this->delete(route('superadmin.contacts.destroy-many'), ['ids' => [$a->id]])->assertSessionHas('success');
    expect(Customer::whereKey([$a->id, $b->id, $c->id, $d->id])->pluck('name')->sort()->values()->all())->toBe(['Budi', 'Cici', 'Dedi']);

    // "Pilih semua yang cocok dengan filter": hanya customer Invoice yang terhapus.
    $this->delete(route('superadmin.contacts.destroy-many', ['app' => 'invoices']), ['all' => 1])->assertSessionHas('success');
    expect(Customer::whereKey([$b->id, $c->id, $d->id])->pluck('name')->all())->toBe(['Budi']);

    $this->delete(route('superadmin.contacts.destroy-many'), [])->assertSessionHasErrors('ids');
});

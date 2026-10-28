<?php

use App\Models\Customer;
use App\Models\User;
use App\Notifications\QueuedResetPassword;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

function excelUpload(array $rows): UploadedFile
{
    $book = new Spreadsheet();
    $book->getActiveSheet()->fromArray($rows);
    $path = tempnam(sys_get_temp_dir(), 'xl').'.xlsx';
    (new Xlsx($book))->save($path);

    return new UploadedFile($path, 'customers.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

it('owner bisa tambah, ubah, dan hapus customer Invoice', function () {
    $branch = appBranch();
    $this->actingAs($branch->user);

    $this->post(route('invoices.customers.store'), ['branch_id' => $branch->id, 'name' => 'Budi', 'phone' => '0812 3456 7890', 'email' => 'Budi@Mail.test'])->assertSessionHas('success');
    $customer = Customer::forApp('invoices')->sole();
    expect($customer->phone)->toBe('6281234567890')->and($customer->email)->toBe('budi@mail.test');

    // Nomor sama di aplikasi yang sama ditolak, di aplikasi lain boleh.
    $this->post(route('invoices.customers.store'), ['branch_id' => $branch->id, 'name' => 'Dobel', 'phone' => '081234567890'])->assertSessionHasErrors('phone');
    $this->post(route('pages.customers.store'), ['branch_id' => $branch->id, 'name' => 'Budi', 'phone' => '081234567890'])->assertSessionHas('success');

    $this->put(route('invoices.customers.update', $customer), ['name' => 'Budi S', 'phone' => '081234567890', 'email' => 'budi.baru@mail.test'])->assertSessionHas('success');
    expect($customer->fresh()->email)->toBe('budi.baru@mail.test');

    $this->delete(route('invoices.customers.destroy', $customer))->assertSessionHas('success');
    expect(Customer::forApp('invoices')->count())->toBe(0)->and(Customer::forApp('pages')->count())->toBe(1);
});

it('impor Excel masuk ke aplikasi menu itu saja, nomor Excel berupa angka tetap utuh', function () {
    $branch = appBranch();
    $this->actingAs($branch->user);

    $file = excelUpload([['Nama', 'WhatsApp', 'Email'], ['Ani', 81111111111, 'ani@mail.test'], ['Tanpa Kontak', null, null], ['Bayu', '082222222222', null]]);
    $this->post(route('pages.customers.import'), ['branch_id' => $branch->id, 'file' => $file])->assertSessionHas('success', fn ($m) => str_contains($m, '2 customer diimpor'));

    expect(Customer::forApp('pages')->pluck('phone')->sort()->values()->all())->toBe(['6281111111111', '6282222222222'])
        ->and(Customer::forApp('invoices')->count())->toBe(0);

    $this->get(route('pages.customers.template'))->assertOk()->assertDownload('contoh-customers-pages.xlsx');
});

it('Jadikan User membuat akun aktif, tipe user, email terverifikasi, dan mengirim link buat password', function () {
    Notification::fake();
    $branch = appBranch();
    $customer = customerIn($branch, 'invoices', 'Citra', '083333333333', 'citra@mail.test');
    $this->actingAs($branch->user);

    $this->post(route('invoices.customers.account', $customer))->assertSessionHas('success');

    $user = User::where('email', 'citra@mail.test')->sole();
    expect($user->name)->toBe('Citra')->and($user->handphone)->toBe('6283333333333')
        ->and($user->status)->toBe(User::STATUS_ACTIVE)->and($user->user_type)->toBe(User::TYPE_USER)
        ->and($user->email_verified_at)->not->toBeNull();
    Notification::assertSentTo($user, QueuedResetPassword::class);

    // Kedua kali: tidak membuat akun baru.
    $this->post(route('invoices.customers.account', $customer))->assertSessionHas('info');
    expect(User::where('email', 'citra@mail.test')->count())->toBe(1);
    $this->get(route('invoices.customers.index'))->assertSee('Punya akun');
});

it('customer tanpa email tidak bisa dijadikan user, dan owner lain tidak bisa mengubah customer kita', function () {
    $branch = appBranch();
    $customer = customerIn($branch, 'pages', 'Dodi', '084444444444');

    $this->actingAs($branch->user)->post(route('pages.customers.account', $customer))->assertSessionHas('error');
    expect(User::where('name', 'Dodi')->exists())->toBeFalse();

    $other = appBranch();
    $this->actingAs($other->user)->delete(route('pages.customers.destroy', $customer))->assertNotFound();
    $this->put(route('pages.customers.update', $customer), ['name' => 'X', 'phone' => '084444444444'])->assertNotFound();
    // Customer Pages tidak bisa dihapus lewat URL menu Invoice.
    $this->actingAs($branch->user)->delete(route('invoices.customers.destroy', $customer))->assertNotFound();
});

it('Phone Book WhatsApp: impor Excel ke group, hapus, dan jadikan user', function () {
    Notification::fake();
    $branch = appBranch();
    $group = tap(new \App\Models\ContactGroup(['name' => 'Promo']), fn ($g) => $g->forceFill(['branch_id' => $branch->id])->save());
    $this->actingAs($branch->user);

    $this->post(route('whatsapp.contacts.import'), ['branch_id' => $branch->id, 'group_id' => $group->id,
        'file' => excelUpload([['nama', 'hp', 'email'], ['Eka', '085555555555', 'eka@mail.test']])])->assertSessionHas('success');
    $eka = Customer::forApp('whatsapp')->sole();
    expect($group->customers()->count())->toBe(1);

    $this->get(route('whatsapp.contacts.index', ['branch' => $branch->id]))->assertOk()->assertSee('Jadikan User')->assertSee('Impor Excel');
    $this->post(route('whatsapp.contacts.account', $eka))->assertSessionHas('success');
    $this->delete(route('whatsapp.contacts.destroy', $eka))->assertSessionHas('success');
    expect(Customer::forApp('whatsapp')->count())->toBe(0)->and(User::where('email', 'eka@mail.test')->exists())->toBeTrue();
});

it('Jadikan User pada customer tanpa email: isi email di form Ubah lalu akun langsung dibuat', function () {
    Notification::fake();
    $branch = appBranch();
    $customer = customerIn($branch, 'pages', 'Fani', '086666666666');
    $this->actingAs($branch->user);

    $this->get(route('pages.customers.index'))->assertSee('make_user', false);

    $this->put(route('pages.customers.update', $customer), ['name' => 'Fani', 'phone' => '086666666666', 'email' => 'fani@mail.test', 'make_user' => '1'])
        ->assertSessionHas('success', fn ($m) => str_contains($m, 'Akun login untuk fani@mail.test'));
    expect($customer->fresh()->email)->toBe('fani@mail.test')->and(User::where('email', 'fani@mail.test')->exists())->toBeTrue();

    // Ubah biasa tidak membuat akun.
    $other = customerIn($branch, 'pages', 'Gita', '087777777777');
    $this->put(route('pages.customers.update', $other), ['name' => 'Gita', 'phone' => '087777777777', 'email' => 'gita@mail.test', 'make_user' => '0']);
    expect(User::where('email', 'gita@mail.test')->exists())->toBeFalse();
});

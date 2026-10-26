<?php

use App\Models\Invoice;
use App\Models\WaApiKey;
use App\Models\WaDevice;
use App\Models\WaFormIntegration;
use App\Models\WaFormSubmission;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

function waBranchWithDevice(array $apps = ['whatsapp']): array
{
    $branch = appBranch($apps);
    $device = tap((new WaDevice())->forceFill(['branch_id' => $branch->id, 'name' => 'CS', 'phone' => '6281200000000', 'status' => 'connected']))->save();

    return [$branch, $device];
}

function gform(array $attributes = [], array $apps = ['whatsapp']): WaFormIntegration
{
    [$branch, $device] = waBranchWithDevice($apps);
    $form = new WaFormIntegration(['name' => 'Seminar', 'device_id' => $device->id, 'phone_field' => 'Nomor WhatsApp', 'name_field' => 'Nama Lengkap',
        'message' => 'Halo {{ Nama Lengkap }}, kelas {{Kelas}} siap! {{Tidak Ada}}', 'is_active' => true, ...$attributes]);
    $form->forceFill(['branch_id' => $branch->id, 'webhook_token' => WaFormIntegration::newToken(),
        'checkin_token' => ($attributes['registration_enabled'] ?? false) ? str_repeat('t', 48) : null, 'checkin_pin_hash' => Hash::make('123456')])->save();

    return $form;
}

function submitForm(WaFormIntegration $form, array $answers = ['Nama Lengkap' => 'Budi', 'Nomor WhatsApp' => '0812 3456 7890', 'Kelas' => 'Piano'])
{
    return test()->postJson(route('wa.google-form.receive', $form->webhook_token), $answers);
}

beforeEach(fn () => Http::fake(['*/v1/messages' => Http::response(['success' => true, 'data' => ['outbox_id' => 'ob-1']])]));

it('menu Integrasi Web: tab API Key per nomor lalu tab dokumentasi; dokumentasi publik tanpa login', function () {
    [$branch, $device] = waBranchWithDevice();
    (new WaApiKey())->forceFill(['branch_id' => $branch->id, 'device_id' => $device->id, 'api_key' => WaApiKey::newApiKey(), 'secret' => 'wsk_x', 'is_active' => true])->save();
    $this->actingAs($branch->user);

    $this->get(route('whatsapp.third-party.index'))->assertOk()->assertSee('API Key per Nomor')->assertSee('Kelola')->assertSee('Dokumentasi API');
    $this->get(route('whatsapp.third-party.index', ['tab' => 'docs']))->assertOk()->assertSee('/api/v1/wa/messages')->assertSee(route('docs.whatsapp-api'));

    auth()->logout();
    $this->get(route('docs.whatsapp-api'))->assertOk()->assertSee(url('/api/v1/wa/messages'))->assertDontSee('{{BASE_URL}}', false);
});

it('menu Google Form: tab pertama daftar, tab terakhir panduan; pengaturan pembayaran & registrasi ulang tervalidasi', function () {
    [$branch, $device] = waBranchWithDevice(['whatsapp', 'invoices']);
    $this->actingAs($branch->user);
    $this->get(route('whatsapp.google-forms.index'))->assertOk()->assertSeeInOrder(['Daftar Google Form', 'Panduan']);
    $this->get(route('whatsapp.google-forms.index', ['tab' => 'panduan']))->assertOk()->assertSee('link_bayar');

    $base = ['branch_id' => $branch->id, 'name' => 'Seminar', 'device_id' => $device->id, 'phone_field' => 'Nomor WA', 'message' => 'Halo', 'is_active' => '1'];
    $this->post(route('whatsapp.google-forms.store'), [...$base, 'payment_enabled' => '1', 'registration_enabled' => '1'])->assertSessionHasErrors(['amount', 'checkin_pin']);

    $this->post(route('whatsapp.google-forms.store'), [...$base, 'payment_enabled' => '1', 'amount' => '150000', 'registration_enabled' => '1', 'checkin_pin' => '482916'])->assertRedirect();
    $form = WaFormIntegration::sole();
    expect($form->amount)->toBe(150000)->and($form->checkin_token)->toHaveLength(48)->and(Hash::check('482916', $form->checkin_pin_hash))->toBeTrue();
    $this->get(route('whatsapp.google-forms.show', [$form, 'tab' => 'script']))->assertOk()->assertSee('function pasang()')->assertSee('onFormSubmit()')->assertSee($form->webhookUrl(), false);
});

it('pembayaran butuh paket Tagihan aktif', function () {
    [$branch, $device] = waBranchWithDevice(['whatsapp']);
    $this->actingAs($branch->user)->post(route('whatsapp.google-forms.store'), ['branch_id' => $branch->id, 'name' => 'X', 'device_id' => $device->id, 'phone_field' => 'WA', 'message' => 'Hi', 'payment_enabled' => '1', 'amount' => '50000'])
        ->assertSessionHasErrors('payment_enabled');
});

it('form tanpa fitur tambahan: dibalas sekali, jawaban disisipkan, kiriman ganda diabaikan', function () {
    $form = gform();
    submitForm($form)->assertOk()->assertJson(['success' => true, 'status' => 'sent']);
    submitForm($form)->assertOk()->assertJson(['status' => 'duplicate']);

    Http::assertSentCount(1);
    Http::assertSent(fn (HttpRequest $r) => $r['to'] === '6281234567890' && $r['text'] === 'Halo Budi, kelas Piano siap! {{Tidak Ada}}');
    expect(WaFormSubmission::sole())->name->toBe('Budi')->status->toBe('sent')->code->toBeNull();
});

it('registrasi ulang tanpa bayar: link QR langsung di pesan, petugas scan sekali saja', function () {
    $form = gform(['registration_enabled' => true]);
    submitForm($form);
    $row = WaFormSubmission::sole();
    Http::assertSent(fn (HttpRequest $r) => str_contains($r['text'], $row->ticketUrl()));
    expect($row->qr_sent_at)->not->toBeNull();

    $this->get($row->ticketUrl())->assertOk()->assertSee($row->code)->assertSee('data:image');

    $check = fn () => $this->postJson(route('form-checkin.check', $form->checkin_token), ['code' => $row->code]);
    $check()->assertUnauthorized();
    $this->get(route('form-checkin.show', $form->checkin_token))->assertSee('Masukkan PIN');
    $this->post(route('form-checkin.login', $form->checkin_token), ['pin' => '000000'])->assertSessionHasErrors('pin');
    $this->post(route('form-checkin.login', $form->checkin_token), ['pin' => '123456'])->assertRedirect();

    $check()->assertJson(['state' => 'ok', 'stats' => ['hadir' => 1, 'total' => 1]]);
    $check()->assertJson(['state' => 'duplicate']);
    $this->postJson(route('form-checkin.check', $form->checkin_token), ['code' => url('/r/'.$row->code)])->assertJson(['state' => 'duplicate']);
    $this->postJson(route('form-checkin.check', $form->checkin_token), ['code' => 'ZZZZZZZZZZ'])->assertJson(['state' => 'invalid']);
});

it('pembayaran + registrasi ulang: link bayar dulu, QR dikirim sekali setelah lunas', function () {
    $form = gform(['registration_enabled' => true, 'payment_enabled' => true, 'amount' => 75000], ['whatsapp', 'invoices']);
    submitForm($form);
    $row = WaFormSubmission::with('invoice')->sole();

    expect($row->invoice)->amount->toBe(75000)->source->toBe(Invoice::SOURCE_FORM)->customer_phone->toBe('6281234567890');
    Http::assertSent(fn (HttpRequest $r) => str_contains($r['text'], $row->invoice->payUrl()) && str_contains($r['text'], 'Rp 75.000') && ! str_contains($r['text'], $row->ticketUrl()));
    $this->get($row->ticketUrl())->assertOk()->assertSee('Bayar sekarang')->assertDontSee('data:image');

    $this->post(route('form-checkin.login', $form->checkin_token), ['pin' => '123456']);
    $this->postJson(route('form-checkin.check', $form->checkin_token), ['code' => $row->code])->assertJson(['state' => 'unpaid']);

    $row->invoice->forceFill(['status' => Invoice::STATUS_PAID, 'paid_at' => now()])->save();
    $row->invoice->forceFill(['paid_amount' => 75000])->save(); // simpan ulang tidak mengirim dobel
    Http::assertSentCount(2);
    Http::assertSent(fn (HttpRequest $r) => str_contains($r['text'], 'sudah kami terima') && str_contains($r['text'], $row->ticketUrl()));

    $this->postJson(route('form-checkin.check', $form->checkin_token), ['code' => $row->code])->assertJson(['state' => 'ok']);
});

it('kata sistem selalu diganti: {{link_qr}} saat menunggu bayar berisi link tiket, fitur mati dikosongkan', function () {
    $form = gform(['registration_enabled' => true, 'payment_enabled' => true, 'amount' => 75000, 'message' => "Halo {{Nama Lengkap:}}\n{{link_qr}} {{kode}}"], ['whatsapp', 'invoices']);
    submitForm($form);
    $row = WaFormSubmission::with('invoice')->sole();
    Http::assertSent(fn (HttpRequest $r) => str_starts_with($r['text'], "Halo Budi\n".$row->ticketUrl().' '.$row->code) && str_contains($r['text'], $row->invoice->payUrl()));

    $plain = gform(['message' => 'Hai {{nama  lengkap}}{{link_bayar}}{{link_qr}}{{kode}}']);
    submitForm($plain, ["Nama\u{00A0}Lengkap " => 'Budi', 'Nomor WhatsApp' => '0812 3456 7890']);
    Http::assertSent(fn (HttpRequest $r) => $r['text'] === 'Hai Budi');
});

it('laporan: tab pendaftar, kirim ulang QR, dan export Excel; owner lain tidak bisa membuka', function () {
    $form = gform(['registration_enabled' => true]);
    submitForm($form);
    submitForm($form, ['Nama Lengkap' => 'Ani']);
    $row = WaFormSubmission::whereNotNull('code')->sole();
    $owner = \App\Models\UserBranch::find($form->branch_id)->user;
    $this->actingAs($owner);

    $this->get(route('whatsapp.google-forms.show', $form))->assertOk()->assertSee('Budi')->assertSee('Nomor tidak ditemukan')->assertSee('Kirim QR');
    $this->post(route('whatsapp.google-forms.resend', [$form, $row]))->assertSessionHas('success');
    $this->get(route('whatsapp.google-forms.export', $form))->assertOk()->assertDownload('laporan-seminar-'.now()->format('Ymd').'.xlsx');

    [$other] = waBranchWithDevice();
    $this->actingAs($other->user)->get(route('whatsapp.google-forms.show', $form))->assertNotFound();
    $this->get(route('whatsapp.google-forms.export', $form))->assertNotFound();
});

it('form nonaktif dan token salah tidak mengirim', function () {
    $form = gform(['is_active' => false]);
    submitForm($form)->assertOk()->assertJson(['success' => false]);
    $this->postJson('/api/v1/wa/google-form/gf_'.str_repeat('a', 48), ['x' => 1])->assertNotFound();
    Http::assertNothingSent();
});

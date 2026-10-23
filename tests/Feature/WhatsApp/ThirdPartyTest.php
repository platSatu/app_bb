<?php

use App\Models\WaApiKey;
use App\Models\WaDevice;
use App\Models\WaFormIntegration;
use App\Models\WaFormSubmission;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

function waBranchWithDevice(): array
{
    $branch = appBranch(['whatsapp']);
    $device = tap((new WaDevice())->forceFill(['branch_id' => $branch->id, 'name' => 'CS', 'phone' => '6281200000000', 'status' => 'connected']))->save();

    return [$branch, $device];
}

function gform(array $attributes = []): WaFormIntegration
{
    [$branch, $device] = waBranchWithDevice();
    $form = new WaFormIntegration(['name' => 'Pendaftaran', 'device_id' => $device->id, 'phone_field' => 'Nomor WhatsApp', 'message' => 'Halo {{ Nama Lengkap }}, kelas {{Kelas}} siap! {{Tidak Ada}}', 'is_active' => true, ...$attributes]);
    $form->forceFill(['branch_id' => $branch->id, 'webhook_token' => WaFormIntegration::newToken()])->save();

    return $form;
}

beforeEach(fn () => Http::fake(['*/v1/messages' => Http::response(['success' => true, 'data' => ['outbox_id' => 'ob-1']])]));

it('halaman Third Party menampilkan API Key per nomor, Google Form, dan link dokumentasi', function () {
    [$branch, $device] = waBranchWithDevice();
    (new WaApiKey())->forceFill(['branch_id' => $branch->id, 'device_id' => $device->id, 'api_key' => WaApiKey::newApiKey(), 'secret' => 'wsk_x', 'is_active' => true])->save();
    $this->actingAs($branch->user);

    $this->get(route('whatsapp.third-party.index'))->assertOk()
        ->assertSee('API Key per Nomor')->assertSee('CS')->assertSee('Kelola')->assertSee('Google Form')->assertSee(route('docs.whatsapp-api'));
    $this->get(route('whatsapp.api-docs'))->assertOk()->assertSee('/api/v1/wa/messages')->assertSee(url('/'));
});

it('dokumentasi publik bisa dibuka tanpa login dan tidak memuat data akun', function () {
    $this->get(route('docs.whatsapp-api'))->assertOk()->assertSee('API WhatsApp bizbos')->assertSee(url('/api/v1/wa/messages'))->assertDontSee('{{BASE_URL}}', false);
});

it('owner bisa menghubungkan Google Form lalu mendapat script dengan URL webhook', function () {
    [$branch, $device] = waBranchWithDevice();
    $this->actingAs($branch->user);

    $this->post(route('whatsapp.third-party.forms.store'), ['branch_id' => $branch->id, 'name' => 'Seminar', 'device_id' => $device->id, 'phone_field' => ' Nomor WA ', 'message' => 'Halo {{Nama}}', 'is_active' => '1'])
        ->assertRedirect();
    $form = WaFormIntegration::sole();
    expect($form->phone_field)->toBe('Nomor WA')->and($form->webhook_token)->toStartWith('gf_');

    $this->get(route('whatsapp.third-party.forms.show', $form))->assertOk()->assertSee('onFormSubmit')->assertSee($form->webhookUrl());

    $old = $form->webhook_token;
    $this->post(route('whatsapp.third-party.forms.regenerate', $form));
    expect($form->fresh()->webhook_token)->not->toBe($old);
});

it('form yang masuk dibalas WhatsApp: nomor dirapikan, jawaban disisipkan, kiriman ganda diabaikan', function () {
    $form = gform();
    $payload = ['Nama Lengkap' => 'Budi', 'nomor whatsapp ' => '0812 3456 7890', 'Kelas' => ['Piano', 'Gitar']];

    $this->postJson(route('wa.google-form.receive', $form->webhook_token), $payload)->assertOk()->assertJson(['success' => true, 'status' => 'sent']);
    $this->postJson(route('wa.google-form.receive', $form->webhook_token), $payload)->assertOk()->assertJson(['status' => 'duplicate']);

    Http::assertSentCount(1);
    Http::assertSent(fn (HttpRequest $r) => $r['to'] === '6281234567890'
        && $r['text'] === 'Halo Budi, kelas Piano, Gitar siap! {{Tidak Ada}}'
        && $r['device_id'] === $form->device_id && str_starts_with($r['client_ref'], 'gform:'));

    expect(WaFormSubmission::sole()->status)->toBe('sent')->and($form->fresh()->trigger_count)->toBe(1);
});

it('nomor tidak ditemukan tercatat gagal dengan penjelasan; form nonaktif dan token salah tidak mengirim', function () {
    $form = gform();
    $this->postJson(route('wa.google-form.receive', $form->webhook_token), ['Nama Lengkap' => 'Ani'])->assertOk()->assertJson(['status' => 'failed']);
    expect(WaFormSubmission::sole()->error)->toContain('Nomor WhatsApp');

    $form->forceFill(['is_active' => false])->save();
    $this->postJson(route('wa.google-form.receive', $form->webhook_token), ['Nomor WhatsApp' => '081111111111'])->assertOk()->assertJson(['success' => false]);
    $this->postJson('/api/v1/wa/google-form/gf_'.str_repeat('a', 48), ['x' => 1])->assertNotFound();

    Http::assertNothingSent();
});

it('owner lain tidak bisa membuka atau mengubah integrasi kita', function () {
    $form = gform();
    [$other] = waBranchWithDevice();

    $this->actingAs($other->user)->get(route('whatsapp.third-party.forms.show', $form))->assertNotFound();
    $this->delete(route('whatsapp.third-party.forms.destroy', $form))->assertNotFound();
    expect(WaFormIntegration::count())->toBe(1);
});

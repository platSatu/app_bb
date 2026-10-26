<?php

use App\Models\BranchSubscription;
use App\Models\User;
use App\Models\WaApiKey;
use App\Models\WaDevice;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Device + API key aktif; mengembalikan [key, secret asli, device]. */
function waApiClient(array $limits = []): array
{
    $branch = appBranch(['whatsapp']);
    if ($limits) {
        BranchSubscription::where('branch_id', $branch->id)->update(['limits' => json_encode($limits)]);
    }
    $device = tap((new WaDevice())->forceFill(['branch_id' => $branch->id, 'name' => 'CS', 'phone' => '6281200000000', 'status' => 'connected']))->save();
    $key = (new WaApiKey())->forceFill(['branch_id' => $branch->id, 'device_id' => $device->id, 'api_key' => WaApiKey::newApiKey(), 'is_active' => true]);
    $secret = $key->issueSecret();
    $key->save();

    return [$key, $secret, $device];
}

function waApiPost(WaApiKey $key, string $secret, array $body)
{
    return test()->withHeaders(['X-WA-Token' => $key->api_key, 'X-WA-Secret' => $secret])->postJson('/api/wa-api/v1/send-message', $body);
}

beforeEach(fn () => Http::fake(['*/v1/messages' => Http::response(['success' => true, 'data' => ['outbox_id' => 'ob-1']])]));

it('format teleios: header X-WA-Token + X-WA-Secret, body to + message, jawaban 200 status sent', function () {
    [$key, $secret] = waApiClient();

    waApiPost($key, $secret, ['to' => '0812-3456-7890', 'message' => 'Halo Budi'])
        ->assertOk()
        ->assertJson(['success' => true, 'status' => 'sent', 'message' => ['id' => 'ob-1', 'to' => '6281234567890', 'queued' => true]]);

    Http::assertSent(fn (HttpRequest $request) => $request['to'] === '6281234567890' && $request['text'] === 'Halo Budi' && $request['device_id'] === $key->device_id);
    expect($key->logs()->count())->toBe(1);
});

it('kirim ke grup memakai ID grup; nomor tidak valid ditolak 422 seperti teleios', function () {
    [$key, $secret] = waApiClient();

    waApiPost($key, $secret, ['to' => '120363025246125486@g.us', 'message' => 'Halo grup'])->assertOk();
    waApiPost($key, $secret, ['to' => '12345', 'message' => 'x'])->assertStatus(422)->assertJsonValidationErrors('to');
    waApiPost($key, $secret, ['to' => '6281234567890'])->assertStatus(422)->assertJsonValidationErrors('message');
});

it('secret hanya disimpan hash-nya; secret salah 401 dan percobaan berulang dari satu IP diblokir', function () {
    [$key, $secret] = waApiClient();

    expect(DB::table('wa_api_keys')->where('id', $key->id)->value('secret_hash'))->toBe(hash('sha256', $secret))
        ->and(json_encode(DB::table('wa_api_keys')->where('id', $key->id)->first()))->not->toContain($secret);

    foreach (range(1, 10) as $i) {
        waApiPost($key, 'wsk_salah', ['to' => '6281234567890', 'message' => 'x'])->assertStatus(401)->assertJson(['error' => 'Token atau Secret Key tidak valid.']);
    }
    // Sudah 10x salah: secret yang benar pun ditahan sebentar dari IP yang sama.
    waApiPost($key, $secret, ['to' => '6281234567890', 'message' => 'x'])->assertStatus(429);
    Http::assertNothingSent();
});

it('API nonaktif atau paket WhatsApp habis ditolak 403; kuota bulanan habis 429', function () {
    [$key, $secret] = waApiClient(['monthly' => 1]);

    waApiPost($key, $secret, ['to' => '6281234567890', 'message' => 'satu'])->assertOk();
    waApiPost($key, $secret, ['to' => '6281234567890', 'message' => 'dua'])->assertStatus(429)->assertJsonPath('success', false);

    $key->forceFill(['is_active' => false])->save();
    waApiPost($key, $secret, ['to' => '6281234567890', 'message' => 'x'])->assertForbidden();

    $key->forceFill(['is_active' => true])->save();
    BranchSubscription::where('branch_id', $key->branch_id)->update(['ends_at' => now()->subDay()]);
    app()->forgetScopedInstances(); // AppAccess di-cache per request; di test semua request berbagi satu aplikasi
    waApiPost($key, $secret, ['to' => '6281234567890', 'message' => 'x'])->assertForbidden()->assertJsonPath('error', 'Paket WhatsApp untuk akun ini belum aktif atau sudah habis.');

    Http::assertSentCount(1);
});

it('usage: paket, sisa kuota, dan jumlah request kirim', function () {
    [$key, $secret] = waApiClient(['monthly' => 100]);
    waApiPost($key, $secret, ['to' => '6281234567890', 'message' => 'Halo'])->assertOk();

    $this->withHeaders(['X-WA-Token' => $key->api_key, 'X-WA-Secret' => $secret])->getJson('/api/wa-api/v1/usage')
        ->assertOk()
        ->assertJson([
            'package_active' => true,
            'quota' => ['unlimited' => false, 'max' => 100, 'used' => 1, 'remaining' => 99],
            'requests' => ['today' => ['sent' => 1, 'total' => 1], 'this_month' => ['sent' => 1]],
        ]);
});

it('owner membuat API key: secret tampil sekali, halaman memakai API Host, Token, Secret Key', function () {
    [, , $device] = waApiClient();
    $device->apiKey()->delete();
    $this->actingAs(User::find($device->branch->user_id));

    $this->post(route('whatsapp.api-key.store', $device))->assertSessionHas('secret');
    $secret = session('secret');
    expect($device->apiKey()->first()->secretMatches($secret))->toBeTrue();

    $this->get(route('whatsapp.api-key.show', $device))->assertOk()->assertSee('API Host')->assertSee('X-WA-Token')->assertSee('/api/wa-api/v1/send-message');
});

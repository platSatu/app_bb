<?php

use App\Models\WaBroadcast;
use App\Models\WaDevice;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

function broadcastDevice(): WaDevice
{
    $branch = appBranch(['whatsapp']);

    return tap((new WaDevice())->forceFill(['branch_id' => $branch->id, 'name' => 'CS', 'phone' => '6281200000000', 'status' => 'connected']))->save();
}

beforeEach(fn () => Http::fake([
    '*/v1/campaigns/*' => Http::response(['success' => true, 'data' => []]),
    '*/v1/devices/*/groups' => Http::response(['success' => true, 'data' => ['groups' => [['jid' => '120363025246125486@g.us', 'name' => 'Ortu Kelas 7', 'participants' => 30, 'is_admin' => false, 'announce' => false]]]]),
    '*/v1/messages' => Http::response(['success' => true, 'data' => ['outbox_id' => 'ob-1']]),
]));

it('sekali kirim sekarang ke nomor dan Grup WhatsApp: masuk antrean, nama grup tersimpan', function () {
    $device = broadcastDevice();
    $this->actingAs($device->branch->user);

    $this->post(route('whatsapp.messages.store'), [
        'type' => 'once', 'schedule' => 'now', 'device_id' => $device->id, 'name' => 'Info', 'body' => 'Halo {nama}',
        'phones' => "081234567890; 6281299998888", 'wa_groups' => ['120363025246125486@g.us'],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $broadcast = WaBroadcast::firstOrFail();
    expect($broadcast->target['wa_groups'])->toBe([['jid' => '120363025246125486@g.us', 'name' => 'Ortu Kelas 7']])
        ->and($broadcast->status)->toBe('done')
        ->and(DB::table('wa_outbox')->pluck('chat_jid')->sort()->values()->all())->toBe(['120363025246125486@g.us', '6281234567890', '6281299998888'])
        ->and(DB::table('wa_outbox')->where('chat_jid', '6281234567890')->value('body'))->toBe('Halo Kak');
    Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/start'));
});

it('bertahap: pesan diurutkan per hari kirim, kirim pertama = tanggal mulai + H terkecil, lalu lanjut ke pesan berikutnya', function () {
    $device = broadcastDevice();
    $this->actingAs($device->branch->user);
    $start = now()->addDay()->toDateString();

    $this->post(route('whatsapp.messages.store'), [
        'type' => 'drip', 'schedule' => 'drip', 'device_id' => $device->id, 'name' => 'Onboarding',
        'start_date' => $start, 'send_time' => '09:00', 'phones' => '6281234567890',
        'steps' => [['delay_days' => 3, 'body' => 'Kedua'], ['delay_days' => 0, 'body' => 'Pertama']],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $broadcast = WaBroadcast::with('steps')->firstOrFail();
    expect($broadcast->steps->pluck('body')->all())->toBe(['Pertama', 'Kedua'])
        ->and($broadcast->next_run_at->toDateTimeString())->toBe(Carbon::parse($start.' 09:00')->toDateTimeString());

    // Waktunya tiba: pesan pertama terkirim, jadwal maju ke H+3.
    $broadcast->forceFill(['next_run_at' => now()->subMinute()])->save();
    $campaign = app(\App\Services\WhatsApp\BroadcastService::class)->run($broadcast->id);
    expect($campaign->name)->toContain('Pesan 1')
        ->and(DB::table('wa_outbox')->value('body'))->toBe('Pertama')
        ->and($broadcast->fresh()->next_run_at->toDateTimeString())->toBe(Carbon::parse($start.' 09:00')->addDays(3)->toDateTimeString())
        ->and($broadcast->fresh()->status)->toBe('active');
});

it('berulang berhenti setelah tanggal berakhir; tanpa tujuan ditolak', function () {
    $device = broadcastDevice();
    $this->actingAs($device->branch->user);

    $this->post(route('whatsapp.messages.store'), [
        'type' => 'recurring', 'schedule' => 'daily', 'device_id' => $device->id, 'name' => 'Harian', 'body' => 'x',
        'start_date' => now()->addDay()->toDateString(), 'send_time' => '08:00',
    ])->assertSessionHasErrors('recipients');

    $broadcast = (new WaBroadcast(['name' => 'Harian', 'type' => 'recurring', 'schedule' => 'daily']))
        ->forceFill(['ends_at' => now()->addDay()->endOfDay()]);
    expect($broadcast->nextAfter(now()->addMinute()))->not->toBeNull()
        ->and($broadcast->nextAfter(now()->addDay()->addMinute()))->toBeNull();
});

it('daftar Message menampilkan Delivered, Read, Failed dari status pesan', function () {
    $device = broadcastDevice();
    $broadcast = tap((new WaBroadcast(['name' => 'Promo', 'type' => 'once', 'schedule' => 'now']))->forceFill([
        'branch_id' => $device->branch_id, 'device_id' => $device->id, 'target' => ['phones' => ['6281']], 'status' => 'done',
    ]))->save();
    $campaign = (string) Str::uuid();
    DB::table('wa_campaigns')->insert(['id' => $campaign, 'branch_id' => $device->branch_id, 'device_id' => $device->id, 'broadcast_id' => $broadcast->id, 'name' => 'x', 'status' => 'done', 'total' => 3, 'created_at' => now(), 'updated_at' => now()]);
    $chat = (string) Str::uuid();
    DB::table('wa_chats')->insert(['id' => $chat, 'branch_id' => $device->branch_id, 'device_id' => $device->id, 'chat_jid' => '6281@s.whatsapp.net', 'created_at' => now(), 'updated_at' => now()]);
    foreach ([['sent', 'read'], ['sent', 'delivered'], ['failed', null]] as $i => [$status, $msgStatus]) {
        $messageId = $msgStatus ? (string) Str::uuid() : null;
        if ($messageId) {
            DB::table('wa_messages')->insert(['id' => $messageId, 'branch_id' => $device->branch_id, 'device_id' => $device->id, 'chat_id' => $chat, 'wa_id' => 'W'.$i, 'type' => 'text', 'status' => $msgStatus, 'sent_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('wa_outbox')->insert(['id' => (string) Str::uuid(), 'branch_id' => $device->branch_id, 'device_id' => $device->id, 'campaign_id' => $campaign, 'chat_jid' => '6281', 'status' => $status, 'message_id' => $messageId, 'created_at' => now(), 'updated_at' => now()]);
    }

    $this->actingAs($device->branch->user)->get(route('whatsapp.messages.index', ['branch' => $device->branch_id]))
        ->assertOk()->assertSee('Buat Pesan')->assertSee('Delivered')->assertSee('2 / 3 terkirim')
        ->assertSeeInOrder(['Promo', '>2<', '>1<', '>1<'], false);
});

it('inbox bisa memulai chat baru ke Grup WhatsApp', function () {
    $device = broadcastDevice();
    $this->actingAs($device->branch->user);

    $this->getJson(route('whatsapp.inbox.groups', $device))->assertOk()->assertJsonPath('groups.0.jid', '120363025246125486@g.us');
    $this->postJson(route('whatsapp.inbox.send', $device), ['group_jid' => '120363025246125486@g.us', 'text' => 'Halo grup'])->assertOk();
    Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/v1/messages') && $r['to'] === '120363025246125486@g.us');
    $this->postJson(route('whatsapp.inbox.send', $device), ['group_jid' => 'bukan-grup', 'text' => 'x'])->assertStatus(422);
});

it('edit: isi & jadwal sekali kirim bisa diubah, status dijeda tetap, pesan selesai tidak bisa diedit', function () {
    $device = broadcastDevice();
    $this->actingAs($device->branch->user);
    $broadcast = tap((new WaBroadcast(['name' => 'Renungan', 'type' => 'once', 'schedule' => 'at']))->forceFill([
        'branch_id' => $device->branch_id, 'device_id' => $device->id, 'body' => 'Lama', 'target' => ['wa_groups' => [['jid' => '120363025246125486@g.us', 'name' => 'Ortu Kelas 7']]],
        'starts_at' => now()->addDay(), 'next_run_at' => now()->addDay(), 'status' => 'paused',
    ]))->save();

    $this->get(route('whatsapp.messages.edit', $broadcast))->assertOk()->assertSee('Edit Jadwal Pesan')->assertSee('Lama');

    $at = now()->addDays(2)->setTime(6, 0);
    $this->put(route('whatsapp.messages.update', $broadcast), [
        'type' => 'drip', 'schedule' => 'at', 'device_id' => $device->id, 'name' => 'Renungan Baru', 'body' => 'Baru',
        'send_at' => $at->format('Y-m-d\TH:i'), 'wa_groups' => ['120363025246125486@g.us'],
    ])->assertRedirect(route('whatsapp.messages.show', $broadcast))->assertSessionHasNoErrors();

    $broadcast->refresh();
    expect($broadcast->type)->toBe('once')->and($broadcast->name)->toBe('Renungan Baru')->and($broadcast->body)->toBe('Baru')
        ->and($broadcast->status)->toBe('paused')->and($broadcast->next_run_at->toDateTimeString())->toBe($at->toDateTimeString());

    $broadcast->forceFill(['status' => 'done'])->save();
    $this->get(route('whatsapp.messages.edit', $broadcast))->assertNotFound();
});

it('edit: berulang yang sudah berjalan dihitung ulang ke jadwal berikutnya; bertahap yang sudah mulai tidak mengubah urutan pesan', function () {
    $device = broadcastDevice();
    $this->actingAs($device->branch->user);
    $recurring = tap((new WaBroadcast(['name' => 'Harian', 'type' => 'recurring', 'schedule' => 'daily']))->forceFill([
        'branch_id' => $device->branch_id, 'device_id' => $device->id, 'body' => 'x', 'target' => ['phones' => ['6281234567890']],
        'starts_at' => now()->subDays(5), 'next_run_at' => now()->addHour(), 'runs_count' => 5, 'status' => 'active',
    ]))->save();

    $this->put(route('whatsapp.messages.update', $recurring), [
        'type' => 'recurring', 'schedule' => 'daily', 'device_id' => $device->id, 'name' => 'Harian', 'body' => 'Isi baru',
        'start_date' => now()->subDays(5)->toDateString(), 'send_time' => '07:30', 'phones' => '6281234567890',
    ])->assertSessionHasNoErrors();
    expect($recurring->fresh()->next_run_at->isFuture())->toBeTrue()
        ->and($recurring->fresh()->next_run_at->format('H:i'))->toBe('07:30');

    $drip = tap((new WaBroadcast(['name' => 'Onboarding', 'type' => 'drip', 'schedule' => 'drip']))->forceFill([
        'branch_id' => $device->branch_id, 'device_id' => $device->id, 'target' => ['phones' => ['6281234567890']],
        'starts_at' => now()->subDay(), 'next_run_at' => now()->addDays(2), 'runs_count' => 1, 'status' => 'active',
    ]))->save();
    foreach ([[1, 0, 'Satu'], [2, 3, 'Dua']] as [$pos, $delay, $body]) {
        (new \App\Models\WaBroadcastStep(['position' => $pos, 'delay_days' => $delay, 'body' => $body]))->forceFill(['broadcast_id' => $drip->id])->save();
    }

    $this->put(route('whatsapp.messages.update', $drip), [
        'type' => 'drip', 'schedule' => 'drip', 'device_id' => $device->id, 'name' => 'Onboarding 2', 'phones' => '6289999999999',
        'steps' => [['delay_days' => 0, 'body' => 'Diganti']],
    ])->assertSessionHasNoErrors();
    $drip->refresh()->load('steps');
    expect($drip->name)->toBe('Onboarding 2')->and($drip->steps->pluck('body')->all())->toBe(['Satu', 'Dua'])
        ->and($drip->target['phones'])->toBe(['6289999999999']);
});

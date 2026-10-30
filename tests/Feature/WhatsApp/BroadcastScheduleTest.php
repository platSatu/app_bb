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

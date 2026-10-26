<?php

use App\Models\User;
use App\Models\UserBranch;
use App\Models\WaChat;
use App\Models\WaDevice;
use Illuminate\Support\Str;

function notifChat(WaDevice $device, array $attributes): WaChat
{
    return tap((new WaChat())->forceFill([
        'branch_id' => $device->branch_id, 'device_id' => $device->id, 'chat_jid' => Str::random(10).'@s.whatsapp.net',
        'phone' => '6281234567890', 'is_group' => false, 'unread_count' => 0, 'last_message_at' => now(), ...$attributes,
    ]))->save();
}

function notifDevice(UserBranch $branch, string $name = 'CS'): WaDevice
{
    return tap((new WaDevice())->forceFill(['branch_id' => $branch->id, 'name' => $name, 'phone' => '6281200000000', 'status' => 'connected']))->save();
}

it('lonceng hanya berisi chat belum dibaca milik owner sendiri, terbaru di atas', function () {
    $branch = appBranch(['whatsapp']);
    $device = notifDevice($branch);
    notifChat($device, ['name' => 'Budi', 'unread_count' => 2, 'last_message_preview' => 'Halo kak', 'last_message_at' => now()->subMinutes(5)]);
    $ani = notifChat($device, ['name' => 'Ani', 'unread_count' => 1, 'last_message_preview' => 'Pesan terbaru']);
    notifChat($device, ['name' => 'Sudah dibaca', 'unread_count' => 0]);
    notifChat($device, ['name' => 'Saluran Berita', 'unread_count' => 9, 'chat_jid' => '123@newsletter']);
    // Chat milik owner lain tidak boleh ikut.
    notifChat(notifDevice(appBranch(['whatsapp'])), ['name' => 'Orang lain', 'unread_count' => 5]);

    $response = $this->actingAs(User::find($branch->user_id))->getJson(route('whatsapp.notifications'))->assertOk();

    expect($response->json('count'))->toBe(2)
        ->and($response->json('items.*.name'))->toBe(['Ani', 'Budi'])
        ->and($response->json('items.0'))->toMatchArray(['device' => 'CS', 'unread' => 1, 'preview' => 'Pesan terbaru',
            'url' => route('whatsapp.inbox.show', [$device->id, 'chat' => $ani->id])]);

    $this->get(route('dashboard'))->assertSee('data-notifications', false);
});

it('tanpa paket WhatsApp aktif lonceng tidak tampil dan endpoint ditolak', function () {
    $branch = appBranch(['pages']);
    $owner = User::find($branch->user_id);

    $this->actingAs($owner)->get(route('dashboard'))->assertDontSee('data-notifications', false);
    $this->getJson(route('whatsapp.notifications'))->assertForbidden();
});

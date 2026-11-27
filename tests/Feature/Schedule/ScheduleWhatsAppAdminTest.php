<?php

use App\Models\ScheduleRescheduleRequest;
use App\Models\User;
use App\Services\WhatsApp\WaGateway;
use Illuminate\Support\Facades\Hash;

/** Chatbot WhatsApp Jadwal tahap 3 (menu admin). Memakai swbSetup / swbSay / swbSubmit dari ScheduleWhatsAppBotTest. */
beforeEach(function () {
    $this->sent = [];
    $this->mock(WaGateway::class, function ($mock) {
        $mock->shouldReceive('sendText')->andReturnUsing(function (string $branchId, array $data) {
            $this->sent[] = $data;

            return ['success' => true];
        });
    });
});

function swaPin(array $s, string $pin = '1234'): void
{
    User::whereKey($s['branch']->user_id)->update(['wa_pin' => Hash::make($pin)]);
}

it('menu admin hanya terbuka untuk nomor owner branch', function () {
    $s = swbSetup();

    expect(swbSay($s['ownerChat'], 'menu'))->toContain('menu admin')->toContain('Rekap absensi')
        ->and(swbSay($s['parentChat'], 'menu'))->toBeNull();
});

it('rekap absensi untuk tanggal pilihan', function () {
    $s = swbSetup();
    swbSay($s['ownerChat'], 'menu');
    swbSay($s['ownerChat'], '1');
    expect(swbSay($s['ownerChat'], '4'))->toContain('Tulis tanggalnya');

    expect(swbSay($s['ownerChat'], today()->addDay()->format('d/m/Y')))->toContain('Rekap absensi')->toContain('Sesi: 1')->toContain('Belum diisi 1');
    expect(swbSay($s['ownerChat'], '0'))->toContain('menu admin');
});

it('kirim pengingat massal wajib PIN: tanpa PIN ditolak, PIN salah dihitung, PIN benar mengirim', function () {
    $s = swbSetup();

    swbSay($s['ownerChat'], 'menu');
    expect(swbSay($s['ownerChat'], '5'))->toContain('1 murid / orang tua');
    expect(swbSay($s['ownerChat'], '1'))->toContain('PIN Anda belum dibuat');

    swaPin($s);
    swbSay($s['ownerChat'], 'menu');
    swbSay($s['ownerChat'], '5');
    expect(swbSay($s['ownerChat'], '1'))->toContain('Masukkan PIN');
    expect(swbSay($s['ownerChat'], '0000'))->toContain('PIN salah')->toContain('Sisa 4');
    expect(swbSay($s['ownerChat'], '1234'))->toContain('1 pesan untuk murid / orang tua');

    expect(collect($this->sent)->where('to', SWB_PARENT)->last()['text'])->toContain('Balas *1*');
});

it('setujui semua pengajuan lewat menu admin dengan PIN', function () {
    $s = swbSetup();
    $request = swbSubmit($s);
    swaPin($s);

    swbSay($s['ownerChat'], 'menu');
    expect(swbSay($s['ownerChat'], '2'))->toContain('1* pengajuan');
    swbSay($s['ownerChat'], '1');
    expect(swbSay($s['ownerChat'], '1234'))->toContain('1 pengajuan disetujui');

    expect($request->refresh()->status)->toBe(ScheduleRescheduleRequest::STATUS_DONE)
        ->and($request->reviewed_via)->toBe('whatsapp');
});

it('link pendaftaran bisa diminta lewat menu admin', function () {
    $s = swbSetup();
    swbSay($s['ownerChat'], 'menu');
    swbSay($s['ownerChat'], '3');

    expect(swbSay($s['ownerChat'], '1'))->toContain('/daftar/')->toContain('Daftar Murid')->toContain('Daftar Pengajar');
});

it('membuat PIN di website wajib kata sandi akun yang benar', function () {
    $s = swbSetup();
    $owner = User::find($s['branch']->user_id);

    $this->actingAs($owner)->put(route('schedules.notifications.pin'), ['current_password' => 'salah', 'pin' => '1234', 'pin_confirmation' => '1234'])
        ->assertSessionHasErrors('current_password');
    expect($owner->refresh()->wa_pin)->toBeNull();

    $this->put(route('schedules.notifications.pin'), ['current_password' => 'password', 'pin' => '1234', 'pin_confirmation' => '1234'])->assertRedirect();
    expect(Hash::check('1234', $owner->refresh()->wa_pin))->toBeTrue();
});

<?php

use App\Models\ScheduleAttendance;
use App\Models\ScheduleRoom;
use App\Models\ScheduleSession;
use App\Models\ScheduleTeacher;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\WaDevice;
use App\Services\Schedule\ScheduleSessionService;
use App\Services\WhatsApp\WaGateway;

/**
 * Chatbot WhatsApp Jadwal tahap 2 (pengajar). Memakai swbSetup / swbSay dari ScheduleWhatsAppBotTest.
 * Titik lokasi branch: Monas (-6.1753924, 106.8271528).
 */
const SWT_TEACHER = '6281300000004';
const SWT_LAT = -6.1753924;
const SWT_LNG = 106.8271528;

beforeEach(function () {
    $this->sent = [];
    $this->mock(WaGateway::class, function ($mock) {
        $mock->shouldReceive('sendText')->andReturnUsing(function (string $branchId, array $data) {
            $this->sent[] = $data;

            return ['success' => true];
        });
    });
});

/** swbSetup + pengajar punya nomor WA & akun portal, branch punya titik lokasi. */
function swtSetup(int $students = 1): array
{
    $s = swbSetup($students);
    $teacherUser = User::factory()->create();
    ScheduleTeacher::whereKey($s['session']->teacher_id)->update(['phone' => SWT_TEACHER, 'user_id' => $teacherUser->id]);
    $s['branch']->forceFill(['latitude' => SWT_LAT, 'longitude' => SWT_LNG])->save();

    return [...$s, 'teacherUser' => $teacherUser, 'teacherChat' => notifChat(WaDevice::first(), ['phone' => SWT_TEACHER, 'name' => 'Bu Sari'])];
}

/** Pengajar scan QR tempat dari halaman Scan Absen (lokasi HP dikirim bersama). */
function swtScanPlace(array $s, ?float $lat = SWT_LAT, ?float $lng = SWT_LNG)
{
    return test()->actingAs($s['teacherUser'])->postJson(route('portal.scan.store'), array_filter([
        'code' => $s['branch']->refresh()->placeQr(), 'lat' => $lat, 'lng' => $lng, 'accuracy' => $lat === null ? null : 20,
    ], fn ($v) => $v !== null));
}

it('pengajar scan QR tempat di lokasi: absen datang tercatat dan daftar murid dikirim ke WhatsApp', function () {
    $s = swtSetup();
    $this->travelTo($s['session']->start_at->copy()->addMinutes(5));

    swtScanPlace($s, SWT_LAT + 0.0003)->assertOk()->assertJsonFragment(['reload' => true]);

    $session = $s['session']->refresh();
    expect($session->teacher_checked_in_at)->not->toBeNull()
        ->and($session->teacher_checkin_via)->toBe('qr')
        ->and($session->teacher_checkin_distance)->toBeLessThan(60)
        ->and(collect($this->sent)->firstWhere('to', SWT_TEACHER)['text'])->toContain('Natan')->toContain('Balas *1*');
});

it('scan QR tempat dari luar lokasi atau tanpa izin lokasi ditolak', function () {
    $s = swtSetup();
    $this->travelTo($s['session']->start_at->copy()->addMinutes(5));

    swtScanPlace($s, SWT_LAT + 0.05)->assertForbidden()->assertJsonFragment(['message' => 'Anda terdeteksi sekitar '.$s['branch']->distanceTo(SWT_LAT + 0.05, SWT_LNG).' m dari tempat les. Absen hanya bisa dilakukan di lokasi ya.']);
    swtScanPlace($s, null, null)->assertForbidden();

    expect($s['session']->refresh()->teacher_checked_in_at)->toBeNull();
});

it('balas 1 = semua hadir: absen tercatat lewat WhatsApp dan sesi ditutup otomatis', function () {
    $s = swtSetup();
    $this->travelTo($s['session']->start_at->copy()->addMinutes(5));
    swtScanPlace($s)->assertOk();

    expect(swbSay($s['teacherChat'], '1'))->toContain('absen sudah tersimpan');

    $attendance = $s['attendance']->refresh();
    expect($attendance->status)->toBe(ScheduleAttendance::STATUS_PRESENT)
        ->and($attendance->marked_via)->toBe('whatsapp')
        ->and($s['session']->refresh()->status)->toBe(ScheduleSession::STATUS_DONE);
});

it('ada yang tidak hadir: pilih murid, pilih alasan, konfirmasi, lalu tersimpan', function () {
    $s = swtSetup(students: 2);
    $this->travelTo($s['session']->start_at->copy()->addMinutes(5));
    swtScanPlace($s)->assertOk();

    $list = swbSay($s['teacherChat'], 'ada yang ga hadir');
    expect($list)->toContain('Siapa yang tidak hadir')->toMatch('/\*(\d)\*\. Natan/');
    preg_match('/\*(\d)\*\. Natan/', $list, $natan);
    expect(swbSay($s['teacherChat'], $natan[1]))->toContain('Sakit / izin');
    expect(swbSay($s['teacherChat'], '1'))->toContain('Izin: Natan');
    expect(swbSay($s['teacherChat'], '1'))->toContain('Izin 1');

    $statuses = ScheduleAttendance::where('session_id', $s['session']->id)->with('student')->get()->pluck('status', 'student.name');
    expect($statuses['Natan'])->toBe(ScheduleAttendance::STATUS_EXCUSED)
        ->and($statuses['Murid 2'])->toBe(ScheduleAttendance::STATUS_PRESENT)
        ->and($s['session']->refresh()->status)->toBe(ScheduleSession::STATUS_DONE);
});

it('nomor selain pengajar sesi itu tidak bisa mengabsen', function () {
    $s = swtSetup();
    $this->travelTo($s['session']->start_at->copy()->addMinutes(5));
    swtScanPlace($s)->assertOk();

    $stranger = notifChat(WaDevice::first(), ['phone' => '6281999999998']);
    expect(swbSay($stranger, '1'))->toBeNull()
        ->and($s['attendance']->refresh()->status)->toBe(ScheduleAttendance::STATUS_PENDING);

    // Nomor pengajar diganti admin: percakapan lama tidak berlaku.
    ScheduleTeacher::whereKey($s['session']->teacher_id)->update(['phone' => '6281300000099']);
    swbSay($s['teacherChat'], '1');
    expect($s['attendance']->refresh()->status)->toBe(ScheduleAttendance::STATUS_PENDING);
});

it('kelas online: pengajar balas 1 untuk absen datang, lalu daftar murid dikirim saat sesi mulai', function () {
    $s = swtSetup();
    ScheduleRoom::whereKey($s['session']->room_id)->update(['is_online' => true]);

    $this->travelTo($s['session']->start_at->copy()->subMinutes(5));
    $this->artisan('schedules:wa-attendance')->assertSuccessful();
    expect(collect($this->sent)->where('to', SWT_TEACHER)->last()['text'])->toContain('sesi online');

    expect(swbSay($s['teacherChat'], '1'))->toContain('kehadiran Kakak tercatat');
    expect($s['session']->refresh()->teacher_checkin_via)->toBe('whatsapp');

    $this->travelTo($s['session']->start_at->copy()->addMinute());
    $this->artisan('schedules:wa-attendance')->assertSuccessful();
    expect(collect($this->sent)->where('to', SWT_TEACHER)->last()['text'])->toContain('Yang belum diabsen');
});

it('rekap H-1 dibalas 2: tercatat berhalangan dan admin dikabari', function () {
    $s = swtSetup();
    $s['branch']->forceFill(['wa_notify' => [...$s['branch']->wa_notify, 'teacher_recap' => true, 'teacher_recap_at' => '00:00']])->save();

    $this->artisan('schedules:remind')->assertSuccessful();
    expect(collect($this->sent)->firstWhere('to', SWT_TEACHER)['text'])->toContain('Balas *2*');

    expect(swbSay($s['teacherChat'], '2'))->toContain('Admin sudah kami beri tahu');
    expect($s['session']->refresh()->teacher_unavailable_at)->not->toBeNull()
        ->and(collect($this->sent)->where('to', SWB_OWNER)->last()['text'])->toContain('Pengajar berhalangan');
});

it('admin memindah jadwal: orang tua ikut dikabari jadwal barunya', function () {
    $s = swtSetup();

    app(ScheduleSessionService::class)->move($s['session'], $s['slot']->toDateString(), '15:00', $s['session']->teacher_id, $s['session']->room_id);

    expect(collect($this->sent)->where('to', SWB_PARENT)->last()['text'])->toContain('ada perubahan jadwal les Natan');
});

it('QR tempat: ganti otomatis memberi masa tenggang, ganti manual langsung mematikan QR lama', function () {
    $s = swtSetup();
    $branch = $s['branch'];
    $old = $branch->placeQr();

    $branch->forceFill(['place_qr_auto_rotate' => true])->save();
    $this->artisan('schedules:rotate-place-qr')->assertSuccessful();
    expect(UserBranch::idFromPlaceQr($old))->toBe($branch->id)
        ->and(UserBranch::idFromPlaceQr($branch->refresh()->placeQr()))->toBe($branch->id);

    $this->travel(UserBranch::PLACE_QR_GRACE_DAYS + 1)->days();
    expect(UserBranch::idFromPlaceQr($old))->toBeNull();

    $current = $branch->refresh()->placeQr();
    $this->actingAs(User::find($branch->user_id))->put(route('settings.branches.place-qr', $branch))->assertRedirect();
    expect(UserBranch::idFromPlaceQr($current))->toBeNull();
});

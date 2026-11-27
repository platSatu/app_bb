<?php

use App\Models\ScheduleAttendance;
use App\Models\ScheduleRescheduleRequest;
use App\Models\ScheduleRoutine;
use App\Models\ScheduleSession;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\WaChat;
use App\Models\WaScheduleConversation;
use App\Services\Schedule\ScheduleSessionService;
use App\Services\WhatsApp\Schedule\ScheduleBot;
use App\Services\WhatsApp\WaGateway;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Chatbot WhatsApp Jadwal tahap 1. Helper appBranch / notifDevice / notifChat / slMaster dipakai bersama tes lain.
 * WhatsApp dipalsukan: setiap pesan keluar dicatat di $this->sent.
 */
const SWB_OWNER = '6281100000001';
const SWB_PARENT = '6281200000002';

beforeEach(function () {
    $this->sent = [];
    $this->mock(WaGateway::class, function ($mock) {
        $mock->shouldReceive('sendText')->andReturnUsing(function (string $branchId, array $data) {
            $this->sent[] = $data;

            return ['success' => true];
        });
    });
});

/** Branch dengan paket Jadwal & WhatsApp, bot nyala, nomor owner terisi, satu sesi les besok untuk Natan (privat). */
function swbSetup(int $students = 1): array
{
    $branch = appBranch(['schedules', 'whatsapp']);
    $branch->forceFill(['wa_notify' => ['schedule_bot' => true, 'schedule' => true, 'schedule_at' => '00:00']])->save();
    User::whereKey($branch->user_id)->update(['handphone' => SWB_OWNER]);
    $device = notifDevice($branch);
    $master = slMaster($branch);
    DB::table('schedule_teacher_grades')->insert(['teacher_id' => $master['teacher_id'], 'grade_id' => $master['grade_id']]);

    $ids = collect(range(1, $students))->map(function ($i) use ($branch) {
        $id = (string) Str::uuid();
        DB::table('schedule_students')->insert(['id' => $id, 'branch_id' => $branch->id, 'name' => $i === 1 ? 'Natan' : 'Murid '.$i,
            'guardian_name' => $i === 1 ? 'Mom Natan' : null, 'phone' => $i === 1 ? SWB_PARENT : '62812000000'.(10 + $i), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    })->all();

    $start = today()->addDay()->setTime(10, 30);
    $session = tap((new ScheduleSession())->forceFill([...$master, 'branch_id' => $branch->id, 'date' => $start->toDateString(), 'start_at' => $start,
        'end_at' => $start->copy()->addMinutes(30), 'status' => ScheduleSession::STATUS_SCHEDULED]))->save();

    foreach ($ids as $id) {
        (new ScheduleAttendance())->forceFill(['session_id' => $session->id, 'student_id' => $id, 'status' => ScheduleAttendance::STATUS_PENDING])->save();
    }

    // Slot kosong pengajar yang sama: jadwal rutin tanpa murid, lusa jam 15.00.
    $slotDay = today()->addDays(2);
    tap((new ScheduleRoutine())->forceFill([...$master, 'branch_id' => $branch->id, 'day_of_week' => $slotDay->dayOfWeekIso, 'start_time' => '15:00:00',
        'end_time' => '15:30:00', 'duration_minutes' => 30, 'capacity' => 1, 'starts_on' => today()->subDay(), 'ends_on' => null, 'status' => 'active']))->save();

    return [
        'branch' => $branch->refresh(),
        'session' => $session,
        'attendance' => ScheduleAttendance::where('session_id', $session->id)->where('student_id', $ids[0])->first(),
        'parentChat' => notifChat($device, ['phone' => SWB_PARENT, 'name' => 'Mom Natan']),
        'ownerChat' => notifChat($device, ['phone' => SWB_OWNER, 'name' => 'Owner']),
        'slot' => $slotDay->copy()->setTime(15, 0),
    ];
}

function swbSay(WaChat $chat, string $text): ?string
{
    return app(ScheduleBot::class)->reply($chat, mb_strtolower(trim($text)), $text)?->text;
}

function swbOpenReminder(array $s): void
{
    app(ScheduleBot::class)->openReminder($s['attendance']->load('student'), $s['session'], false);
}

/** Orang tua: 2 -> alasan -> 1 (ganti hari) -> 1 (slot pertama). */
function swbSubmit(array $s): ScheduleRescheduleRequest
{
    swbOpenReminder($s);
    swbSay($s['parentChat'], '2');
    swbSay($s['parentChat'], 'Ada acara keluarga');
    swbSay($s['parentChat'], '1');
    swbSay($s['parentChat'], '1');

    return ScheduleRescheduleRequest::firstOrFail();
}

it('pengingat H-1 berisi pilihan balasan dan membuka percakapan', function () {
    $s = swbSetup();

    $this->artisan('schedules:remind')->assertSuccessful();

    $message = collect($this->sent)->firstWhere('to', SWB_PARENT);
    expect($message['text'])->toContain('Balas *1*')->toContain('Balas *2*')
        ->and(WaScheduleConversation::where('phone', SWB_PARENT)->value('step'))->toBe(WaScheduleConversation::STEP_CONFIRM);
});

it('balas 1 = konfirmasi hadir tercatat, status absensi tidak diubah', function () {
    $s = swbSetup();
    swbOpenReminder($s);

    expect(swbSay($s['parentChat'], '1'))->toContain('konfirmasinya sudah kami catat');

    $attendance = $s['attendance']->refresh();
    expect($attendance->response)->toBe(ScheduleAttendance::RESPONSE_CONFIRMED)
        ->and($attendance->responded_via)->toBe('whatsapp')
        ->and($attendance->status)->toBe(ScheduleAttendance::STATUS_PENDING)
        ->and(WaScheduleConversation::count())->toBe(0);
});

it('kalimat bebas "ga bisa" dipahami sebagai berhalangan, lalu alasan dicatat sebagai Izin', function () {
    $s = swbSetup();
    swbOpenReminder($s);

    expect(swbSay($s['parentChat'], 'aduh maaf besok ga bisa'))->toContain('alasannya');
    expect(swbSay($s['parentChat'], 'Ada acara keluarga'))->toContain('ajukan ganti hari');

    $attendance = $s['attendance']->refresh();
    expect($attendance->status)->toBe(ScheduleAttendance::STATUS_EXCUSED)
        ->and($attendance->notes)->toContain('Ada acara keluarga');
});

it('ganti hari: jadwal kosong ditawarkan, pengajuan menunggu admin, dan admin dikirimi WhatsApp', function () {
    $s = swbSetup();
    swbOpenReminder($s);
    swbSay($s['parentChat'], '2');
    swbSay($s['parentChat'], 'Sakit');

    expect(swbSay($s['parentChat'], '1'))->toContain($s['slot']->translatedFormat('l, d M').' · 15.00');
    expect(swbSay($s['parentChat'], '1'))->toContain('sudah kami kirim ke admin');

    $request = ScheduleRescheduleRequest::firstOrFail();
    expect($request->status)->toBe(ScheduleRescheduleRequest::STATUS_PENDING)
        ->and($request->mode)->toBe(ScheduleRescheduleRequest::MODE_MOVE)
        ->and($request->slot_start_at->equalTo($s['slot']))->toBeTrue()
        ->and(collect($this->sent)->firstWhere('to', SWB_OWNER)['text'])->toContain('Pengajuan ganti hari')->toContain('Natan');
});

it('admin setuju lewat WhatsApp: kelas privat dipindah dan orang tua dikabari', function () {
    $s = swbSetup();
    $request = swbSubmit($s);

    expect(swbSay($s['ownerChat'], '1'))->toContain('disetujui');

    $session = $s['session']->refresh();
    expect($request->refresh()->status)->toBe(ScheduleRescheduleRequest::STATUS_DONE)
        ->and($request->reviewed_via)->toBe('whatsapp')
        ->and($session->start_at->equalTo($s['slot']))->toBeTrue()
        ->and($s['attendance']->refresh()->status)->toBe(ScheduleAttendance::STATUS_PENDING)
        ->and(collect($this->sent)->where('to', SWB_PARENT)->last()['text'])->toContain('disetujui');
});

it('admin tolak dengan alasan: orang tua menerima alasannya, izin tetap tercatat', function () {
    $s = swbSetup();
    $request = swbSubmit($s);

    expect(swbSay($s['ownerChat'], '2'))->toContain('alasan');
    expect(swbSay($s['ownerChat'], 'Jadwal pengajar penuh'))->toContain('ditolak');

    expect($request->refresh()->status)->toBe(ScheduleRescheduleRequest::STATUS_REJECTED)
        ->and($s['session']->refresh()->start_at->isSameDay(today()->addDay()))->toBeTrue()
        ->and($s['attendance']->refresh()->status)->toBe(ScheduleAttendance::STATUS_EXCUSED)
        ->and(collect($this->sent)->where('to', SWB_PARENT)->last()['text'])->toContain('Jadwal pengajar penuh');
});

it('kelas grup: disetujui dulu, sesi pengganti dibuat otomatis saat sesi lama ditutup', function () {
    $s = swbSetup(students: 2);
    $request = swbSubmit($s);
    expect($request->mode)->toBe(ScheduleRescheduleRequest::MODE_MAKEUP);

    swbSay($s['ownerChat'], '1');
    expect($request->refresh()->status)->toBe(ScheduleRescheduleRequest::STATUS_APPROVED);

    $this->travelTo($s['session']->end_at->copy()->addMinutes(5));
    $statuses = $s['session']->attendances()->pluck('student_id')->mapWithKeys(fn ($id) => [$id => $id === $s['attendance']->student_id ? 'excused' : 'present'])->all();
    app(ScheduleSessionService::class)->complete($s['session'], $statuses, null, User::find($s['branch']->user_id));

    $request->refresh();
    expect($request->status)->toBe(ScheduleRescheduleRequest::STATUS_DONE)
        ->and($request->makeup_session_id)->not->toBeNull()
        ->and(ScheduleSession::find($request->makeup_session_id)->start_at->equalTo($s['slot']))->toBeTrue();
});

it('chat biasa, nomor lain, dan bot yang dimatikan tidak tersentuh', function () {
    $s = swbSetup();

    // Belum ada pengingat: pesan biasa diteruskan ke alur lama.
    expect(swbSay($s['parentChat'], '1'))->toBeNull();

    swbOpenReminder($s);
    $stranger = notifChat(\App\Models\WaDevice::first(), ['phone' => '6281999999999']);
    expect(swbSay($stranger, '2'))->toBeNull();

    $s['branch']->forceFill(['wa_notify' => ['schedule_bot' => false]])->save();
    expect(swbSay($s['parentChat'], '2'))->toBeNull()
        ->and($s['attendance']->refresh()->status)->toBe(ScheduleAttendance::STATUS_PENDING);
});

it('hanya nomor owner yang bisa membuka dan menjawab pengajuan', function () {
    $s = swbSetup();
    swbSubmit($s);

    // Orang tua mengetik "pengajuan": bukan admin.
    expect(swbSay($s['parentChat'], 'pengajuan'))->toBeNull();

    // Nomor owner diganti: percakapan admin lama tidak berlaku.
    User::whereKey($s['branch']->user_id)->update(['handphone' => '6281300000003']);
    expect(swbSay($s['ownerChat'], '1'))->toBeNull()
        ->and(ScheduleRescheduleRequest::first()->status)->toBe(ScheduleRescheduleRequest::STATUS_PENDING);
});

it('pengajuan bisa disetujui di website, tapi tidak oleh owner branch lain', function () {
    $s = swbSetup();
    $request = swbSubmit($s);
    $other = User::find(appBranch(['schedules'])->user_id);

    $this->actingAs($other)->post(route('schedules.reschedules.approve', $request))->assertNotFound();

    $this->actingAs(User::find($s['branch']->user_id))
        ->get(route('schedules.reschedules.index'))->assertOk()->assertSee('Natan');
    $this->post(route('schedules.reschedules.approve', $request))->assertRedirect();

    expect($request->refresh()->status)->toBe(ScheduleRescheduleRequest::STATUS_DONE)
        ->and($request->reviewed_via)->toBe('web');
});

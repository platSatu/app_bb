<?php

use App\Models\BranchSubscription;
use App\Models\ScheduleAttendance;
use App\Models\ScheduleRescheduleRequest;
use App\Models\ScheduleRoutine;
use App\Models\ScheduleSession;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserCompany;
use App\Models\WaChat;
use App\Models\WaDevice;
use App\Services\WhatsApp\Schedule\ScheduleBot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Data uji bersama tes chatbot WhatsApp Jadwal (ScheduleWhatsApp*Test). Bukan file tes (tidak berakhiran Test.php);
 * dimuat dengan require_once, jadi tiap file tes bisa dijalankan sendiri. Nama fungsi berawalan swb supaya tidak
 * bentrok dengan helper di tes lain.
 */
const SWB_OWNER = '6281100000001';
const SWB_PARENT = '6281200000002';

/** Owner + perusahaan + branch dengan paket aplikasi $apps aktif sebulan. */
function swbBranch(array $apps): UserBranch
{
    $owner = User::factory()->create();
    $company = tap((new UserCompany())->forceFill(['user_id' => $owner->id, 'name' => 'PT Uji', 'slug' => 'uji-'.Str::lower(Str::random(5))]))->save();
    $branch = tap((new UserBranch())->forceFill(['user_id' => $owner->id, 'company_id' => $company->id, 'name' => 'Pusat', 'status' => 'active']))->save();

    foreach ($apps as $app) {
        (new BranchSubscription())->forceFill(['branch_id' => $branch->id, 'app_key' => $app, 'starts_at' => now()->subDay(), 'ends_at' => now()->addMonth()])->save();
    }

    return $branch;
}

function swbDevice(UserBranch $branch): WaDevice
{
    return tap((new WaDevice())->forceFill(['branch_id' => $branch->id, 'name' => 'CS', 'phone' => '6281200000000', 'status' => 'connected']))->save();
}

function swbChat(WaDevice $device, array $attributes): WaChat
{
    return tap((new WaChat())->forceFill([
        'branch_id' => $device->branch_id, 'device_id' => $device->id, 'chat_jid' => Str::random(10).'@s.whatsapp.net',
        'phone' => '6281234567890', 'is_group' => false, 'unread_count' => 0, 'last_message_at' => now(), ...$attributes,
    ]))->save();
}

/** Ruangan, bidang, kategori, grade (Rp 400.000 / 4x, 30 menit) & pengajar aktif. */
function swbMaster(UserBranch $branch): array
{
    $id = fn () => (string) Str::uuid();
    $now = now();
    [$room, $subject, $category, $grade, $teacher] = [$id(), $id(), $id(), $id(), $id()];

    DB::table('schedule_rooms')->insert(['id' => $room, 'branch_id' => $branch->id, 'name' => 'Studio A', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('schedule_subjects')->insert(['id' => $subject, 'branch_id' => $branch->id, 'room_id' => $room, 'name' => 'Piano', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('schedule_categories')->insert(['id' => $category, 'branch_id' => $branch->id, 'subject_id' => $subject, 'name' => 'Pop', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('schedule_grades')->insert(['id' => $grade, 'branch_id' => $branch->id, 'category_id' => $category, 'name' => 'Grade A', 'price' => 400000,
        'meetings_per_month' => 4, 'duration_minutes' => 30, 'teacher_percent' => 50, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('schedule_teachers')->insert(['id' => $teacher, 'branch_id' => $branch->id, 'name' => 'Bu Sari', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);

    return ['room_id' => $room, 'grade_id' => $grade, 'teacher_id' => $teacher];
}

/** Branch dengan paket Jadwal & WhatsApp, bot nyala, nomor owner terisi, satu sesi les besok untuk Natan (privat). */
function swbSetup(int $students = 1): array
{
    $branch = swbBranch(['schedules', 'whatsapp']);
    $branch->forceFill(['wa_notify' => ['schedule_bot' => true, 'schedule' => true, 'schedule_at' => '00:00']])->save();
    User::whereKey($branch->user_id)->update(['handphone' => SWB_OWNER]);
    $device = swbDevice($branch);
    $master = swbMaster($branch);
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
        'parentChat' => swbChat($device, ['phone' => SWB_PARENT, 'name' => 'Mom Natan']),
        'ownerChat' => swbChat($device, ['phone' => SWB_OWNER, 'name' => 'Owner']),
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

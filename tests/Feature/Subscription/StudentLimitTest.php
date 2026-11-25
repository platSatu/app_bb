<?php

use App\Exceptions\PlanLimitException;
use App\Models\BranchSubscription;
use App\Models\ScheduleRoutine;
use App\Models\UserBranch;
use App\Services\Schedule\ScheduleRoutineService;
use App\Services\Subscription\PlanLimits;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Branch dengan paket Jadwal aktif; $max null = tanpa batas murid. */
function slBranch(?int $max): UserBranch
{
    $branch = appBranch(['schedules']);
    BranchSubscription::where('branch_id', $branch->id)->where('app_key', 'schedules')
        ->update(['limits' => json_encode($max === null ? [] : ['students' => $max])]);

    return $branch;
}

/** Ruangan, grade & pengajar minimal untuk membuat jadwal. */
function slMaster(UserBranch $branch): array
{
    $id = fn () => (string) Str::uuid();
    $now = now();
    $room = $id();
    $subject = $id();
    $category = $id();
    $grade = $id();
    $teacher = $id();

    DB::table('schedule_rooms')->insert(['id' => $room, 'branch_id' => $branch->id, 'name' => 'Ruang '.Str::random(4), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('schedule_subjects')->insert(['id' => $subject, 'branch_id' => $branch->id, 'room_id' => $room, 'name' => 'Bahasa', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('schedule_categories')->insert(['id' => $category, 'branch_id' => $branch->id, 'subject_id' => $subject, 'name' => 'Inggris', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('schedule_grades')->insert(['id' => $grade, 'branch_id' => $branch->id, 'category_id' => $category, 'name' => 'Basic', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('schedule_teachers')->insert(['id' => $teacher, 'branch_id' => $branch->id, 'name' => 'Bu Sari', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);

    return ['room_id' => $room, 'grade_id' => $grade, 'teacher_id' => $teacher];
}

/** @return list<string> id murid aktif baru */
function slStudents(UserBranch $branch, int $count): array
{
    return collect(range(1, $count))->map(function () use ($branch) {
        $id = (string) Str::uuid();
        DB::table('schedule_students')->insert(['id' => $id, 'branch_id' => $branch->id, 'name' => 'Murid '.Str::random(5), 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    })->all();
}

/** Jadwal bulanan bulan ini (tanpa membuat sesi), peserta dicatat langsung. */
function slRoutine(UserBranch $branch, array $master, array $studentIds, array $override = []): ScheduleRoutine
{
    $routine = new ScheduleRoutine();
    $routine->forceFill([
        'branch_id' => $branch->id, ...$master, 'day_of_week' => 1, 'start_time' => '15:00:00', 'end_time' => '16:00:00',
        'duration_minutes' => 60, 'capacity' => 20, 'starts_on' => today()->startOfMonth(), 'ends_on' => today()->endOfMonth(), 'status' => 'active',
        ...$override,
    ])->save();

    foreach ($studentIds as $id) {
        DB::table('schedule_participants')->insert(['routine_id' => $routine->id, 'student_id' => $id, 'joined_on' => $routine->starts_on->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
    }

    return $routine;
}

/** Cek kuota seperti ScheduleRoutineService::save (di dalam transaksi). */
function slAssert(ScheduleRoutine $routine, array $studentIds): void
{
    DB::transaction(fn () => app(PlanLimits::class)->assertStudents($routine, $studentIds));
}

it('menghitung murid aktif saat ini: satu murid di beberapa jadwal = 1, salinan bulan depan tidak dobel', function () {
    $branch = slBranch(10);
    $master = slMaster($branch);
    [$a, $b, $c] = slStudents($branch, 3);

    slRoutine($branch, $master, [$a, $b]);
    slRoutine($branch, $master, [$a, $c], ['day_of_week' => 3]);
    // Salinan bulan depan dengan murid yang sama.
    slRoutine($branch, $master, [$a, $b, $c], ['starts_on' => today()->addMonthNoOverflow()->startOfMonth(), 'ends_on' => today()->addMonthNoOverflow()->endOfMonth()]);

    $quota = app(PlanLimits::class)->studentQuota($branch->id);

    expect($quota)->toMatchArray(['max' => 10, 'used' => 3, 'remaining' => 7, 'full' => false, 'warn' => false])
        ->and(app(PlanLimits::class)->summary($branch->id)['schedules'][0])->toBe('Murid aktif: 3 / 10');
});

it('tidak menghitung murid yang sudah keluar, murid Tidak Aktif, jadwal nonaktif, dan jadwal yang sudah berakhir', function () {
    $branch = slBranch(10);
    $master = slMaster($branch);
    [$active, $left, $inactive, $paused, $ended, $later] = slStudents($branch, 6);

    $routine = slRoutine($branch, $master, [$active, $left, $inactive, $later]);
    DB::table('schedule_participants')->where('student_id', $left)->update(['left_on' => today()->toDateString()]);
    // Dijadwalkan keluar minggu depan: masih ikut sampai tanggalnya.
    DB::table('schedule_participants')->where('student_id', $later)->update(['left_on' => today()->addWeek()->toDateString()]);
    DB::table('schedule_students')->where('id', $inactive)->update(['status' => 'inactive', 'left_on' => today()->toDateString()]);
    slRoutine($branch, $master, [$paused], ['status' => 'inactive']);
    slRoutine($branch, $master, [$ended], ['starts_on' => today()->subMonths(2)->startOfMonth(), 'ends_on' => today()->subMonths(2)->endOfMonth()]);

    expect(app(PlanLimits::class)->activeStudentIds($branch->id)->sort()->values()->all())
        ->toBe(collect([$active, $later])->sort()->values()->all());
});

it('menolak murid baru saat kuota penuh, dengan pesan yang jelas', function () {
    $branch = slBranch(3);
    $master = slMaster($branch);
    [$a, $b, $c, $d, $e] = slStudents($branch, 5);
    $routine = slRoutine($branch, $master, [$a, $b]);

    // Masih ada 1 kursi: boleh.
    slAssert($routine, [$a, $b, $c]);

    // Menambah 2 murid baru padahal sisa 1.
    expect(fn () => slAssert($routine, [$a, $b, $d, $e]))
        ->toThrow(PlanLimitException::class, 'Sisa kuota murid aktif tinggal 1, sedangkan jadwal ini menambah 2 murid baru.');

    // Kuota penuh.
    DB::table('schedule_participants')->insert(['routine_id' => $routine->id, 'student_id' => $c, 'joined_on' => today()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
    $other = new ScheduleRoutine(['status' => 'active', 'ends_on' => today()->endOfMonth()]);
    $other->forceFill(['branch_id' => $branch->id]);
    expect(fn () => slAssert($other, [$d]))->toThrow(PlanLimitException::class, 'Kuota murid aktif paket Anda sudah penuh: 3 murid sedang ikut jadwal, batas paketnya 3 murid.');
});

it('saat penuh tetap boleh: menyimpan ulang, mengeluarkan, menukar murid, dan memasukkan murid lama ke kelas lain', function () {
    $branch = slBranch(2);
    $master = slMaster($branch);
    [$a, $b, $c] = slStudents($branch, 3);
    $routine = slRoutine($branch, $master, [$a, $b]);
    $other = slRoutine($branch, $master, [], ['day_of_week' => 5]);

    slAssert($routine, [$a, $b]);        // simpan ulang
    slAssert($routine, [$a]);            // mengeluarkan murid
    slAssert($routine, [$a, $c]);        // murid b keluar, c masuk: tetap 2
    slAssert($other, [$a, $b]);          // a & b sudah terhitung, ikut kelas tambahan tidak menambah kuota

    expect(true)->toBeTrue();
});

it('setelah turun paket, murid lama tetap jalan tapi murid baru ditahan sampai di bawah batas', function () {
    $branch = slBranch(2);
    $master = slMaster($branch);
    [$a, $b, $c, $d] = slStudents($branch, 4);
    $routine = slRoutine($branch, $master, [$a, $b, $c]);   // 3 murid, paket sekarang hanya 2

    slAssert($routine, [$a, $b, $c]);                       // edit jadwal tanpa menambah murid: boleh
    slAssert($routine, [$a, $b]);                           // mengurangi: boleh
    expect(fn () => slAssert($routine, [$a, $b, $c, $d]))->toThrow(PlanLimitException::class);
});

it('paket tanpa batas murid dan id murid dari branch lain tidak terhitung', function () {
    $free = slBranch(null);
    $master = slMaster($free);
    $routine = slRoutine($free, $master, slStudents($free, 5));
    slAssert($routine, slStudents($free, 50));
    expect(app(PlanLimits::class)->studentQuota($free->id))->toBeNull();

    $branch = slBranch(1);
    $own = slRoutine($branch, slMaster($branch), slStudents($branch, 1));
    // Id murid branch lain diabaikan (tidak bisa dipakai menembus / mengacaukan hitungan).
    slAssert($own, [...DB::table('schedule_participants')->where('routine_id', $own->id)->pluck('student_id')->all(), ...slStudents($free, 3)]);
    expect(app(PlanLimits::class)->studentQuota($branch->id))->toMatchArray(['used' => 1, 'full' => true]);
});

it('terpasang di simpan jadwal: murid baru ditolak dan tidak ada yang tersimpan', function () {
    Bus::fake();
    $branch = slBranch(1);
    $master = slMaster($branch);
    [$a, $b] = slStudents($branch, 2);
    $routine = slRoutine($branch, $master, [$a]);

    $data = $routine->only(['grade_id', 'teacher_id', 'room_id', 'day_of_week', 'duration_minutes', 'capacity', 'starts_on', 'ends_on', 'status', 'notes']);
    $data['start_time'] = '15:00';

    expect(fn () => app(ScheduleRoutineService::class)->save($routine->fresh(), $data, [$a, $b]))
        ->toThrow(PlanLimitException::class, 'Kuota murid aktif paket Anda sudah penuh');

    expect(DB::table('schedule_participants')->where('routine_id', $routine->id)->pluck('student_id')->all())->toBe([$a]);
});

it('murid yang dinonaktifkan dengan tanggal keluar nanti tetap terhitung sampai tanggalnya', function () {
    $branch = slBranch(2);
    $master = slMaster($branch);
    [$a, $b, $c] = slStudents($branch, 3);
    slRoutine($branch, $master, [$a, $b]);
    $later = today()->addWeek()->toDateString();
    DB::table('schedule_students')->where('id', $b)->update(['status' => 'inactive', 'left_on' => $later]);
    DB::table('schedule_participants')->where('student_id', $b)->update(['left_on' => $later]);

    $new = slRoutine($branch, $master, [], ['day_of_week' => 2]);
    expect(app(PlanLimits::class)->studentQuota($branch->id)['used'])->toBe(2)
        ->and(fn () => slAssert($new, [$c]))->toThrow(PlanLimitException::class);
});

it('superadmin bisa mengisi batas murid aktif di form paket', function () {
    $request = \App\Http\Requests\Superadmin\Package\PackageRequest::create('/', 'POST', ['limits' => ['schedules' => ['students' => '150'], 'invoices' => ['monthly' => '']]]);
    (fn () => $this->prepareForValidation())->call($request);

    expect($request->input('limits.schedules'))->toBe(['students' => '150'])
        ->and($request->input('limits.invoices'))->toBe([])
        ->and((new \App\Http\Requests\Superadmin\Package\PackageRequest())->rules())->toHaveKey('limits.schedules.students');
});

<?php

namespace App\Services\Schedule;

use App\Jobs\SendWaNotification;
use App\Models\ScheduleAttendance;
use App\Models\ScheduleSession;
use App\Models\ScheduleTeacher;
use App\Models\UserBranch;
use App\Services\WhatsApp\Schedule\ScheduleBot;
use App\Services\WhatsApp\Schedule\TeacherBot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Pengingat WhatsApp Jadwal ke murid / orang tua & rekap mengajar ke pengajar. Dipakai perintah terjadwal
 * schedules:remind dan menu admin WhatsApp (kirim manual), dengan penanda yang sama: setiap penerima hanya
 * dikirimi sekali per sesi / per hari, jadi kirim manual tidak membuat pesan dobel.
 * Chatbot Jadwal nyala = pengingat bisa dibalas (lihat ScheduleBot / TeacherBot).
 */
class ScheduleReminderService
{
    public function __construct(private readonly ScheduleBot $students, private readonly TeacherBot $teachers)
    {
    }

    /** Murid / orang tua di sesi pada rentang $between. $soon = pengingat sesaat sebelum les. */
    public function students(UserBranch $branch, array $between, bool $soon): int
    {
        $sent = 0;

        $this->sessions($branch, $between)->chunkById(100, function ($sessions) use (&$sent, $soon) {
            foreach ($sessions as $session) {
                foreach ($session->attendances->where('status', ScheduleAttendance::STATUS_PENDING) as $attendance) {
                    if ($attendance->student?->phone
                        && $this->once(($soon ? 'schedule-soon:' : 'schedule-remind:').$attendance->getKey(), fn () => $this->lesson($session, $attendance, $soon))) {
                        $sent++;
                    }
                }
            }
        });

        return $sent;
    }

    /** Rekap mengajar: satu pesan per pengajar berisi semua sesinya pada rentang $between. */
    public function teachers(UserBranch $branch, array $between): int
    {
        $sent = 0;

        $this->sessions($branch, $between)->get()->sortBy('start_at')->groupBy('teacher_id')->each(function ($sessions) use (&$sent) {
            $teacher = $sessions->first()->teacher;

            if ($teacher?->phone && $this->once('schedule-recap:'.$teacher->getKey().':'.today()->toDateString(), fn () => $this->recap($teacher, $sessions->values()))) {
                $sent++;
            }
        });

        return $sent;
    }

    /** Jumlah murid & pengajar yang akan dikirimi (untuk konfirmasi sebelum kirim manual). */
    public function audience(UserBranch $branch, array $between): array
    {
        $sessions = $this->sessions($branch, $between)->get();

        return [
            'students' => $sessions->flatMap->attendances->where('status', ScheduleAttendance::STATUS_PENDING)->filter(fn ($a) => $a->student?->phone)->count(),
            'teachers' => $sessions->pluck('teacher')->filter(fn ($t) => $t?->phone)->unique('id')->count(),
        ];
    }

    private function lesson(ScheduleSession $session, ScheduleAttendance $attendance, bool $soon): bool
    {
        $interactive = $this->students->canPrompt($session->branch_id, $attendance->student->phone);

        if (! SendWaNotification::lesson($session, $attendance->student, $soon, $interactive)) {
            return false;
        }

        $interactive && $this->students->openReminder($attendance, $session, $soon);

        return true;
    }

    private function recap(ScheduleTeacher $teacher, Collection $sessions): bool
    {
        $interactive = $this->teachers->canPrompt($sessions->first()->branch_id, $teacher->phone);

        if (! SendWaNotification::teacherRecap($teacher, $sessions, $interactive)) {
            return false;
        }

        $interactive && $this->teachers->openRecap($teacher, $sessions);

        return true;
    }

    private function sessions(UserBranch $branch, array $between): Builder
    {
        return ScheduleSession::with(['branch:id,name', 'teacher:id,name,phone', 'room:id,name', 'grade:id,name,category_id', 'grade.category:id,name,subject_id', 'grade.category.subject:id,name',
            'attendances' => fn ($q) => $q->where('status', '!=', ScheduleAttendance::STATUS_EXCUSED)->with('student:id,name,phone,guardian_name')])
            ->where('branch_id', $branch->getKey())
            ->where('status', ScheduleSession::STATUS_SCHEDULED)
            ->whereBetween('start_at', $between);
    }

    /** Kirim sekali saja per penanda. Gagal kirim melepas penandanya lagi, supaya putaran berikutnya mencoba lagi. */
    private function once(string $key, callable $send): bool
    {
        if (! Cache::add($key, 1, now()->addDays(2))) {
            return false;
        }

        if ($send()) {
            return true;
        }

        Cache::forget($key);

        return false;
    }
}

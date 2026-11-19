<?php

namespace App\Console\Commands;

use App\Jobs\SendWaNotification;
use App\Models\ScheduleAttendance;
use App\Models\ScheduleSession;
use App\Models\UserBranch;
use App\Services\Subscription\AppAccess;
use App\Support\ScheduleNotice;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * Pengingat WhatsApp aplikasi Jadwal, dijalankan tiap 5 menit (paket WhatsApp & Jadwal aktif, sakelar di Schedules › Notifikasi):
 *  - murid / orang tua H-1, mulai jam yang dipilih branch (bawaan 16:00);
 *  - murid / orang tua sesaat sebelum les (bawaan 1 jam sebelumnya);
 *  - rekap mengajar besok untuk pengajar, mulai jam yang dipilih branch (bawaan 18:00).
 * Tiap penerima hanya dikirimi sekali per sesi / per hari (penanda di cache), jadi aman dijalankan berulang.
 */
class SendScheduleReminders extends Command
{
    protected $signature = 'schedules:remind';

    protected $description = 'Kirim pengingat les ke murid & rekap mengajar ke pengajar lewat WhatsApp';

    public function handle(): int
    {
        $sent = 0;
        $now = now()->format('H:i');
        $tomorrow = [today()->addDay(), today()->addDay()->endOfDay()];

        UserBranch::whereIn('id', AppAccess::activeBranches('schedules'))->whereIn('id', AppAccess::activeBranches('whatsapp'))
            ->whereNotNull('wa_notify')->get(['id', 'wa_notify'])
            ->each(function (UserBranch $branch) use (&$sent, $now, $tomorrow) {
                $on = fn (string $kind) => ! empty($branch->wa_notify[$kind]);

                if ($on('schedule') && $now >= ScheduleNotice::setting($branch, 'schedule_at')) {
                    $sent += $this->remindStudents($branch, $tomorrow, false);
                }

                if ($on('schedule_soon')) {
                    $sent += $this->remindStudents($branch, [now(), now()->addMinutes((int) ScheduleNotice::setting($branch, 'schedule_soon_minutes'))], true);
                }

                if ($on('teacher_recap') && $now >= ScheduleNotice::setting($branch, 'teacher_recap_at')) {
                    $this->sessions($branch, $tomorrow)->get()->sortBy('start_at')->groupBy('teacher_id')->each(function ($sessions) use (&$sent) {
                        $teacher = $sessions->first()->teacher;

                        if ($teacher?->phone && $this->once('schedule-recap:'.$teacher->getKey().':'.today()->toDateString(), fn () => SendWaNotification::teacherRecap($teacher, $sessions->values()))) {
                            $sent++;
                        }
                    });
                }
            });

        $this->info("{$sent} pesan pengingat Jadwal diantrekan.");

        return self::SUCCESS;
    }

    private function remindStudents(UserBranch $branch, array $between, bool $soon): int
    {
        $sent = 0;

        $this->sessions($branch, $between)->chunkById(100, function ($sessions) use (&$sent, $soon) {
            foreach ($sessions as $session) {
                foreach ($session->attendances->where('status', ScheduleAttendance::STATUS_PENDING) as $attendance) {
                    if ($attendance->student?->phone
                        && $this->once(($soon ? 'schedule-soon:' : 'schedule-remind:').$attendance->getKey(), fn () => SendWaNotification::lesson($session, $attendance->student, $soon))) {
                        $sent++;
                    }
                }
            }
        });

        return $sent;
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

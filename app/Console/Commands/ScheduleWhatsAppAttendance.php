<?php

namespace App\Console\Commands;

use App\Models\ScheduleAttendance;
use App\Models\ScheduleSession;
use App\Models\UserBranch;
use App\Services\Subscription\AppAccess;
use App\Services\WhatsApp\Schedule\ScheduleBot;
use App\Services\WhatsApp\Schedule\TeacherBot;
use App\Support\ScheduleNotice;
use Illuminate\Console\Command;

/**
 * Absen lewat chatbot WhatsApp Jadwal, dijalankan tiap menit (hanya branch yang sakelar chatbot-nya nyala):
 *  1. kelas online menjelang mulai -> pengajar diminta balas 1 untuk absen datang;
 *  2. sesi yang sudah dimulai & pengajarnya sudah absen datang -> daftar murid yang belum diabsen dikirim ke pengajar;
 *  3. sesi yang semua muridnya sudah diabsen lewat WhatsApp -> ditutup otomatis (complete(), honor dihitung).
 * Setiap pesan hanya sekali per sesi (penanda di TeacherBot), jadi aman dijalankan berulang.
 */
class ScheduleWhatsAppAttendance extends Command
{
    protected $signature = 'schedules:wa-attendance';

    protected $description = 'Absen pengajar & murid lewat chatbot WhatsApp Jadwal';

    public function handle(TeacherBot $bot): int
    {
        $sent = 0;
        $closed = 0;

        $branches = UserBranch::whereIn('id', AppAccess::activeBranches('schedules'))->whereIn('id', AppAccess::activeBranches('whatsapp'))
            ->whereNotNull('wa_notify')->get(['id', 'wa_notify'])
            ->filter(fn (UserBranch $branch) => ! empty($branch->wa_notify[ScheduleNotice::BOT]) && ScheduleBot::enabled($branch))
            ->modelKeys();

        if ($branches === []) {
            return self::SUCCESS;
        }

        $scheduled = fn () => ScheduleSession::whereIn('branch_id', $branches)->where('status', ScheduleSession::STATUS_SCHEDULED);

        $scheduled()->whereNull('teacher_checked_in_at')
            ->whereHas('room', fn ($q) => $q->where('is_online', true))
            ->where('start_at', '<=', now()->addMinutes(TeacherBot::ONLINE_EARLY_MINUTES))->where('end_at', '>', now())
            ->orderBy('start_at')->limit(200)->get()
            ->each(function (ScheduleSession $session) use ($bot, &$sent) {
                $bot->promptOnlineStart($session) && $sent++;
            });

        $scheduled()->whereNotNull('teacher_checked_in_at')
            ->where('start_at', '<=', now())->where('end_at', '>=', now()->subHours(TeacherBot::ATTEND_GRACE_HOURS))
            ->whereHas('attendances', fn ($q) => $q->where('status', ScheduleAttendance::STATUS_PENDING))
            ->orderBy('start_at')->limit(200)->get()
            ->each(function (ScheduleSession $session) use ($bot, &$sent) {
                $bot->promptAttendance($session) && $sent++;
            });

        $scheduled()->where('start_at', '<=', now())
            ->whereDoesntHave('attendances', fn ($q) => $q->where('status', ScheduleAttendance::STATUS_PENDING))
            ->whereHas('attendances', fn ($q) => $q->where('marked_via', 'whatsapp'))
            ->limit(200)->get()
            ->each(function (ScheduleSession $session) use ($bot, &$closed) {
                $bot->closeIfReady($session) && $closed++;
            });

        $this->info("Pesan absen: {$sent}, sesi ditutup: {$closed}");

        return self::SUCCESS;
    }
}

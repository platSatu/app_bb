<?php

namespace App\Console\Commands;

use App\Jobs\SendWaNotification;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\Subscription\AppAccess;
use App\Support\ScheduleNotice;
use Illuminate\Console\Command;

/**
 * Ganti QR tempat les otomatis (tiap awal bulan), hanya branch yang menyalakan "Ganti QR otomatis" di Settings › Branch.
 * QR lama masih diterima UserBranch::PLACE_QR_GRACE_DAYS hari, dan owner diingatkan lewat WhatsApp untuk mencetak ulang.
 */
class RotatePlaceQr extends Command
{
    protected $signature = 'schedules:rotate-place-qr';

    protected $description = 'Ganti QR tempat les otomatis untuk branch yang menyalakannya';

    public function handle(): int
    {
        $rotated = 0;

        UserBranch::with('user:id,handphone')
            ->where('place_qr_auto_rotate', true)
            ->whereIn('id', AppAccess::activeBranches('schedules'))
            ->get()
            ->each(function (UserBranch $branch) use (&$rotated) {
                $branch->rotatePlaceQr(auto: true);
                $rotated++;

                SendWaNotification::queue($branch->getKey(), ScheduleNotice::BOT, User::normalizePhone($branch->user?->handphone),
                    "🔄 QR absen pengajar di {$branch->name} sudah diganti otomatis (awal bulan).\n\n"
                    .'QR lama masih bisa dipakai sampai '.$branch->place_qr_grace_until->translatedFormat('d M Y').'. '
                    ."Unduh & cetak QR baru di menu Settings › Branch, lalu tempel menggantikan yang lama ya 🙏\n\n"
                    .route('settings.branches.edit', $branch).'#qr-tempat');
            });

        $this->info("QR tempat diganti: {$rotated}");

        return self::SUCCESS;
    }
}

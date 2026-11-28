<?php

namespace App\Console\Commands;

use App\Models\SubscriptionHistory;
use App\Models\User;
use App\Models\UserBranch;
use App\Models\UserCompany;
use App\Services\Subscription\AppAccess;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Branch internal milik superadmin + paket WhatsApp tanpa batas waktu, supaya superadmin bisa memakai
 * layanan WhatsApp untuk Bizbos sendiri persis seperti owner yang berlangganan.
 * Tanpa invoice/pembayaran (tercatat "Diatur admin"), tidak menyentuh branch tenant. Aman dijalankan ulang.
 */
class SetupSuperadminWhatsApp extends Command
{
    protected $signature = 'whatsapp:superadmin-branch
        {email : Email superadmin}
        {--company=Bizbos : Nama perusahaan internal (dipakai hanya kalau superadmin belum punya perusahaan)}
        {--branch=Bizbos Official : Nama branch internal}';

    protected $description = 'Buat branch internal superadmin dengan paket WhatsApp aktif (tanpa batas waktu)';

    public function handle(SubscriptionService $subscriptions, AppAccess $access): int
    {
        $user = User::where('email', strtolower((string) $this->argument('email')))->first();

        if (! $user?->isSuperadmin()) {
            $this->error('User tidak ditemukan atau bukan superadmin.');

            return self::FAILURE;
        }

        $branchName = trim((string) $this->option('branch')) ?: 'Bizbos Official';

        $branch = DB::transaction(function () use ($user, $branchName) {
            // Satu user satu perusahaan (unique user_id): pakai yang sudah ada kalau ada.
            $company = UserCompany::where('user_id', $user->getKey())->lockForUpdate()->first();

            if (! $company) {
                $company = new UserCompany(['name' => trim((string) $this->option('company')) ?: 'Bizbos', 'email' => $user->email]);
                $company->forceFill(['user_id' => $user->getKey(), 'status' => UserCompany::STATUS_ACTIVE])->save();
                $this->line("Perusahaan dibuat: {$company->name}");
            }

            $branch = UserBranch::where('user_id', $user->getKey())->where('company_id', $company->getKey())->where('name', $branchName)->first();

            if (! $branch) {
                $branch = new UserBranch(['name' => $branchName, 'status' => UserBranch::STATUS_ACTIVE]);
                $branch->forceFill(['user_id' => $user->getKey(), 'company_id' => $company->getKey()])->save();
                $this->line("Branch dibuat: {$branch->name}");
            }

            return $branch;
        });

        if ($access->branchActive($branch->getKey(), 'whatsapp')) {
            $this->info("Paket WhatsApp di {$branch->name} sudah aktif. Tidak ada yang diubah.");

            return self::SUCCESS;
        }

        // $days null = selamanya. Tanpa paket/voucher: tidak ada invoice, batas pesan tidak berlaku.
        $subscriptions->extend($branch, 'whatsapp', null, SubscriptionHistory::SOURCE_MANUAL, $user, null, null, 'Layanan internal superadmin');

        $this->info("Paket WhatsApp aktif di {$branch->name}. Menu WhatsApp tampil di sidebar superadmin.");

        return self::SUCCESS;
    }
}

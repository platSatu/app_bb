<?php

namespace App\Services\Ai;

use App\Models\AiSetting;
use App\Models\AiUsage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Kuota harian pemakaian AI per user (biaya API ditanggung bizbos). Tim platform tidak dibatasi. */
class AiQuota
{
    public function remaining(User $user): ?int
    {
        if ($user->isPlatformTeam()) {
            return null;
        }

        $used = (int) AiUsage::where('user_id', $user->getKey())->whereDate('date', today())->value('count');

        return max(0, AiSetting::current()->daily_quota - $used);
    }

    /** Pakai 1 kuota sebelum memanggil AI. @throws RuntimeException kalau kuota hari ini habis. */
    public function consume(User $user): void
    {
        if ($user->isPlatformTeam()) {
            return;
        }

        DB::transaction(function () use ($user) {
            // Kunci baris user: dua klik bersamaan tidak bisa melewati kuota atau bentrok saat baris hari ini dibuat.
            User::whereKey($user->getKey())->lockForUpdate()->first();
            $usage = AiUsage::where('user_id', $user->getKey())->whereDate('date', today())->lockForUpdate()->first();

            if (! $usage) {
                $usage = new AiUsage();
                $usage->forceFill(['user_id' => $user->getKey(), 'date' => today(), 'count' => 0]);
            }

            $quota = AiSetting::current()->daily_quota;

            if ($quota === 0) {
                throw new RuntimeException('Fitur AI sedang dinonaktifkan oleh admin. Silakan isi konten secara manual dulu ya.');
            }

            if ($usage->count >= $quota) {
                throw new RuntimeException("Kuota AI hari ini sudah habis ({$quota}x). Besok bisa dipakai lagi, atau lanjutkan edit secara manual.");
            }

            $usage->count++;
            $usage->save();
        });
    }

    /** AI gagal = kuota dikembalikan, supaya user tidak rugi. */
    public function refund(User $user): void
    {
        if (! $user->isPlatformTeam()) {
            AiUsage::where('user_id', $user->getKey())->whereDate('date', today())->where('count', '>', 0)->decrement('count');
        }
    }
}

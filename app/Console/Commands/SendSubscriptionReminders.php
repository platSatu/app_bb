<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionReminderMail;
use App\Models\BranchSubscription;
use App\Models\SubscriptionReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Email ke owner saat paket akan habis: H-7, H-4, H-1, dan hari H.
 * Satu email per owner per hari (semua layanan/cabang yang habis di hari yang sama digabung).
 * Diperpanjang = ends_at berubah = siklus pengingat baru.
 */
class SendSubscriptionReminders extends Command
{
    public const DAYS = [7, 4, 1, 0];

    protected $signature = 'subscriptions:remind';

    protected $description = 'Kirim email pengingat paket yang akan habis (H-7, H-4, H-1, hari H)';

    public function handle(): int
    {
        $due = BranchSubscription::with('branch.user')
            ->whereBetween('ends_at', [today(), today()->addDays(max(self::DAYS))->endOfDay()])
            ->get()
            ->filter(fn ($s) => $s->branch?->user?->email && in_array($this->daysBefore($s), self::DAYS, true))
            // Klaim dulu; kalau command jalan dua kali, pengingat tetap terkirim sekali.
            ->filter(fn ($s) => SubscriptionReminder::query()->insertOrIgnore([
                'id' => (string) Str::orderedUuid(), 'subscription_id' => $s->id, 'ends_at' => $s->ends_at,
                'days_before' => $this->daysBefore($s), 'created_at' => now(), 'updated_at' => now(),
            ]) > 0);

        $sent = 0;

        foreach ($due->groupBy(fn ($s) => $s->branch->user_id.'|'.$this->daysBefore($s)) as $items) {
            $owner = $items->first()->branch->user;

            try {
                Mail::to($owner->email)->queue(new SubscriptionReminderMail($owner->id, $this->daysBefore($items->first()), $items->modelKeys()));
                $sent++;
            } catch (Throwable $e) {
                // Gagal masuk antrean: lepas klaim supaya dicoba lagi di jadwal berikutnya hari ini.
                SubscriptionReminder::whereIn('subscription_id', $items->modelKeys())->where('days_before', $this->daysBefore($items->first()))->delete();
                report($e);
            }
        }

        $this->info("{$sent} email pengingat paket dikirim.");

        return self::SUCCESS;
    }

    private function daysBefore(BranchSubscription $subscription): int
    {
        return (int) today()->diffInDays($subscription->ends_at->copy()->startOfDay(), false);
    }
}

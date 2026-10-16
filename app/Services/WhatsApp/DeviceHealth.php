<?php

namespace App\Services\WhatsApp;

use App\Models\WaDevice;
use Illuminate\Support\Facades\DB;

/** Skor 0-100 seberapa aman nomor dari pembatasan WhatsApp, dari riwayat 7 hari terakhir. */
class DeviceHealth
{
    public function for(WaDevice $device): array
    {
        $since = now()->subDays(7);

        $events = DB::table('wa_device_events')->where('device_id', $device->id)->where('created_at', '>=', $since)
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $outbox = DB::table('wa_outbox')->where('device_id', $device->id)->where('created_at', '>=', $since)
            ->whereIn('status', ['sent', 'failed', 'skipped'])
            ->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        $sent = (int) ($outbox['sent'] ?? 0);
        $failed = (int) ($outbox['failed'] ?? 0) + (int) ($outbox['skipped'] ?? 0);
        $failRate = $sent + $failed > 0 ? round($failed / ($sent + $failed) * 100, 1) : 0;

        $banned = (int) ($events['banned'] ?? 0) + ($device->banned_until?->isFuture() ? 1 : 0);
        $loggedOut = (int) ($events['logged_out'] ?? 0);
        $disconnected = (int) ($events['disconnected'] ?? 0);

        $score = max(0, 100 - min(40, $banned * 40) - min(30, $loggedOut * 15) - min(20, $disconnected * 2) - min(20, (int) round($failRate / 2)));

        $cap = $device->daily_cap ?: (int) config('services.wa.blast_daily_cap', 200);
        if ($device->warmup_started_at) {
            $cap = min($cap, 20 * ((int) $device->warmup_started_at->diffInDays(now()) + 1));
        }

        return [
            'score' => $score,
            'label' => $score >= 80 ? 'Sehat' : ($score >= 50 ? 'Perlu diperhatikan' : 'Berisiko'),
            'color' => $score >= 80 ? 'success' : ($score >= 50 ? 'warning' : 'danger'),
            'banned' => $banned,
            'banned_until' => $device->banned_until?->isFuture() ? $device->banned_until->translatedFormat('d M Y H:i') : null,
            'logged_out' => $loggedOut,
            'disconnected' => $disconnected,
            'sent' => $sent,
            'failed' => $failed,
            'fail_rate' => $failRate,
            'sent_today' => $device->sent_date?->isToday() ? (int) $device->sent_today : 0,
            'daily_cap' => $cap,
            'warmup_day' => $device->warmup_started_at ? (int) $device->warmup_started_at->diffInDays(now()) + 1 : null,
        ];
    }
}

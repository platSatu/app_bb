<?php

use App\Http\Controllers\Superadmin\Monitor\MonitorController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::command('deposits:expire')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('tickets:expire')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('catalog:expire')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('schedules:generate')->dailyAt('00:30')->withoutOverlapping();
// Pengingat Jadwal (murid H-1, murid sebelum les, rekap pengajar): dicek tiap 5 menit, jam kirimnya diatur per branch di Schedules › Notifikasi.
Schedule::command('schedules:remind')->everyFiveMinutes()->withoutOverlapping();
// Chatbot WhatsApp Jadwal: "mulai" kelas online, daftar absen murid ke pengajar, dan tutup sesi yang sudah diabsen lewat WhatsApp.
Schedule::command('schedules:wa-attendance')->everyMinute()->withoutOverlapping();
// Ganti QR tempat les otomatis tiap awal bulan (hanya branch yang menyalakannya di Settings › Branch).
Schedule::command('schedules:rotate-place-qr')->monthlyOn(1, '00:05')->withoutOverlapping();
Schedule::command('invoices:expire')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('invoices:remind')->dailyAt('07:55')->withoutOverlapping();
Schedule::command('subscriptions:remind')->dailyAt('08:10')->withoutOverlapping();
Schedule::command('referrals:release')->hourly()->withoutOverlapping();
Schedule::command('whatsapp:dispatch-messages')->everyMinute()->withoutOverlapping();
// Sapa kontak yang menggantung di tengah chatbot flow, tutup percakapan bila tetap didiamkan.
Schedule::command('whatsapp:nudge-sessions')->everyMinute()->withoutOverlapping();
// Agent Prospek: dicek tiap 15 menit, bekerja sekali sehari setelah jam yang diatur (lihat ProspectAgent::run()).
Schedule::call(fn () => app(\App\Services\Marketing\ProspectAgent::class)->run())->everyFifteenMinutes()->name('prospect-agent')->withoutOverlapping();
// Konten Harian: kirim konten yang jadwalnya tiba, cek status kiriman & video yang sedang dibuat, media bertahap sesuai kuota.
Schedule::command('content:publish')->everyMinute()->withoutOverlapping(10);
// Akun Sosmed: perpanjang token TikTok/YouTube sebelum habis, cek token Instagram/Facebook sekali sehari.
Schedule::command('social:refresh-tokens')->hourly()->withoutOverlapping();
// Token toko marketplace diperpanjang sebelum habis (Lazada ~7 hari, Shopee hanya 4 jam), jadi dicek tiap jam.
Schedule::command('marketplace:refresh-tokens')->hourly()->withoutOverlapping();
Schedule::command('db:backup')->dailyAt('01:00')->withoutOverlapping();
// Riwayat aktivitas pengguna & riwayat email lebih dari 180 hari dihapus (KEEP_DAYS di masing-masing model).
Schedule::command('model:prune', ['--model' => [\App\Models\UserActivityLog::class, \App\Models\EmailLog::class]])->dailyAt('02:30')->withoutOverlapping();
// Tanda scheduler hidup, dibaca menu Settings > Antrean & Tugas.
Schedule::call(fn () => Cache::forever(MonitorController::HEARTBEAT, now()->toIso8601String()))->everyMinute()->name('heartbeat');

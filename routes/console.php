<?php

use App\Http\Controllers\Superadmin\Monitor\MonitorController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Schedule::command('deposits:expire')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('tickets:expire')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('catalog:expire')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('schedules:generate')->dailyAt('00:30')->withoutOverlapping();
Schedule::command('invoices:expire')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('invoices:remind')->dailyAt('07:55')->withoutOverlapping();
Schedule::command('subscriptions:remind')->dailyAt('08:10')->withoutOverlapping();
Schedule::command('referrals:release')->hourly()->withoutOverlapping();
Schedule::command('whatsapp:dispatch-messages')->everyMinute()->withoutOverlapping();
Schedule::command('db:backup')->dailyAt('01:00')->withoutOverlapping();
// Riwayat aktivitas pengguna & riwayat email lebih dari 180 hari dihapus (KEEP_DAYS di masing-masing model).
Schedule::command('model:prune', ['--model' => [\App\Models\UserActivityLog::class, \App\Models\EmailLog::class]])->dailyAt('02:30')->withoutOverlapping();
// Tanda scheduler hidup, dibaca menu Settings > Antrean & Tugas.
Schedule::call(fn () => Cache::forever(MonitorController::HEARTBEAT, now()->toIso8601String()))->everyMinute()->name('heartbeat');

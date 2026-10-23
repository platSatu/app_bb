<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('deposits:expire')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('tickets:expire')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('schedules:generate')->dailyAt('00:30')->withoutOverlapping();
Schedule::command('invoices:expire')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('invoices:remind')->dailyAt('07:55')->withoutOverlapping();
Schedule::command('subscriptions:remind')->dailyAt('08:10')->withoutOverlapping();
Schedule::command('whatsapp:dispatch-messages')->everyMinute()->withoutOverlapping();
Schedule::command('db:backup')->dailyAt('01:00')->withoutOverlapping();

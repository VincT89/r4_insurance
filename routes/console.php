<?php

use Illuminate\Support\Facades\Schedule;
Schedule::command('insurance:deadline-reminders')->dailyAt('07:00')->timezone('Europe/Rome')->withoutOverlapping();

Schedule::command('system:scheduler-heartbeat')->everyMinute();
Schedule::command('system:dispatch-queue-heartbeats')->everyMinute()->withoutOverlapping();
Schedule::command('queue:work --queue=default --stop-when-empty --tries=3 --timeout=600')
    ->everyMinute()->name('process-core-queue')->withoutOverlapping(15)->onOneServer()->environments(['production']);
Schedule::command('system:prune-operational-logs')->dailyAt('02:30')->withoutOverlapping(120)->onOneServer();

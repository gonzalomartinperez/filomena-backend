<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Laravel\Pulse\PulseServiceProvider;
use Laravel\Telescope\TelescopeServiceProvider;
use Spatie\Health\Commands\DispatchQueueCheckJobsCommand;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Health\Commands\ScheduleCheckHeartbeatCommand;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('queue:prune-batches', ['--hours' => 24])->daily();
if (class_exists(TelescopeServiceProvider::class) && app()->isLocal()) {
    Schedule::command('telescope:prune', ['--hours' => 24])->daily();
}
if (class_exists(PulseServiceProvider::class)) {
    Schedule::command('pulse:purge')->daily();
}

Schedule::command(RunHealthChecksCommand::class)->everyMinute();
Schedule::command(DispatchQueueCheckJobsCommand::class)->everyMinute();
Schedule::command(ScheduleCheckHeartbeatCommand::class)->everyMinute();

Schedule::command('activitylog:clean')->weeklyOn(1, '03:00');

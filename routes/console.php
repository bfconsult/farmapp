<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('jobs:generate-recurring')->daily();
Schedule::command('metrics:generate-measurements')->daily();
Schedule::command('maintenance:generate-jobs')->daily();
Schedule::command('reminders:send')->daily();
Schedule::command('reports:send-weekly-summary')->weeklyOn(1, '07:00');
Schedule::command('reports:send-weekly-worker-summary')->weeklyOn(1, '07:00');

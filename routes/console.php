<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reminders:generate')->dailyAt('02:00')->withoutOverlapping();

if (config('database.default') === 'sqlite') {
    Schedule::command('db:backup')->dailyAt('01:00')->withoutOverlapping();
}

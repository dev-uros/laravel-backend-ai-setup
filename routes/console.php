<?php

use App\Models\LoginAttempt;
use App\Models\UserLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Both log tables are pruned, on very different clocks: LoginAttempt keeps six
 * months, UserLog three years. How long lives on each model as RETENTION_DAYS,
 * so this one command needs no opinion of its own -- it just runs whatever the
 * models say.
 *
 * Run by the `scheduler` container in compose.yaml (`php artisan
 * schedule:work`). Each tick is a fresh process, so a change here takes effect
 * without restarting anything. Times are in APP_TIMEZONE, which is UTC.
 */
Schedule::command('model:prune', ['--model' => [LoginAttempt::class, UserLog::class]])->daily();

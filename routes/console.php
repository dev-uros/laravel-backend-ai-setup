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

/**
 * Proof that the scheduler is still firing, not merely still running.
 *
 * `schedule:work` dying takes its container with it, and `restart: unless-stopped`
 * brings it straight back. The failure worth catching is the opposite one: the
 * process alive but no longer dispatching, which no process check can see. The
 * container healthcheck reads this file's age, so a stalled scheduler shows up as
 * unhealthy in `make prod-ps` instead of going quiet for a month.
 *
 * A file rather than the cache on purpose -- the healthcheck is a shell test on
 * mtime, with no PHP process to boot every minute. Named `schedule-*` so the
 * existing rule in storage/framework/.gitignore already covers it.
 */
Schedule::call(fn () => touch(storage_path('framework/schedule-heartbeat')))->everyMinute();

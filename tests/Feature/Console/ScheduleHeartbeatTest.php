<?php

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * The scheduler container's healthcheck is a shell test on the age of one file,
 * so this test guards a contract that lives half in PHP and half in YAML: the
 * path in `routes/console.php` and the path in `compose.yaml` /
 * `compose.prod.yaml` have to be the same string, and the schedule has to touch
 * it every minute or the healthcheck reports a stall that never happened.
 *
 * Nothing else in the application reads this file, so nothing else would notice
 * it being renamed or fired on a slower clock.
 */
class ScheduleHeartbeatTest extends TestCase
{
    private const PATH = 'framework/schedule-heartbeat';

    /**
     * The one entry on the every-minute clock. Matching on the expression rather
     * than on a description keeps this pointing at the heartbeat even as other
     * tasks are added, as long as they are not also every-minute.
     */
    private function heartbeat(): ?Event
    {
        return collect(app(Schedule::class)->events())
            ->first(fn ($event) => $event->expression === '* * * * *');
    }

    public function test_the_heartbeat_runs_every_minute(): void
    {
        $this->assertNotNull($this->heartbeat(), 'No every-minute task is scheduled.');
    }

    public function test_running_it_touches_the_file_the_healthcheck_reads(): void
    {
        $path = storage_path(self::PATH);
        $existed = file_exists($path);

        if ($existed) {
            unlink($path);
        }

        $this->heartbeat()->run($this->app);

        $this->assertFileExists($path, 'The healthcheck reads '.self::PATH.'; the schedule wrote something else.');

        if (! $existed) {
            unlink($path);
        }
    }

    /**
     * The file is written every minute for the life of the container, so a name
     * the ignore rules do not cover turns up as a permanently dirty worktree.
     */
    public function test_the_file_is_ignored_by_git(): void
    {
        $this->assertStringContainsString(
            'schedule-*',
            file_get_contents(storage_path('framework/.gitignore')),
        );
    }
}

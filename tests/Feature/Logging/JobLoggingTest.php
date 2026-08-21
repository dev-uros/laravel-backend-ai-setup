<?php

namespace Tests\Feature\Logging;

use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class JobLoggingTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, MessageLogged> */
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->logged = [];

        Event::listen(MessageLogged::class, function (MessageLogged $event): void {
            $this->logged[] = $event;
        });
    }

    /**
     * @return array<int, MessageLogged>
     */
    private function lines(string $message): array
    {
        return array_values(array_filter($this->logged, fn (MessageLogged $e): bool => $e->message === $message));
    }

    private function line(string $message): MessageLogged
    {
        $lines = $this->lines($message);

        $this->assertNotEmpty($lines, "no line with message [{$message}]");

        return $lines[0];
    }

    /**
     * The activation email is a queued notification, so creating a user runs a
     * real job -- the one place in this application where the queue is on the
     * happy path.
     */
    private function createUser(User $admin): void
    {
        $this->actingAsAdmin($admin)
            ->postJson(route('users.store'), [
                'name' => 'Petar',
                'surname' => 'Petrović',
                'email' => 'petar@gmail.com',
            ])
            ->assertCreated();
    }

    public function test_a_finished_job_writes_one_line_with_its_identity_and_duration(): void
    {
        $this->createUser(User::factory()->admin()->create());

        $line = $this->line('job');

        $this->assertSame('info', $line->level);
        /**
         * The notification, not Illuminate's SendQueuedNotifications wrapper:
         * resolveName() asks the job for its display name, and a queued
         * notification answers with the class a person would recognise.
         */
        $this->assertSame(AccountCreatedNotification::class, $line->context['job']);
        $this->assertSame('processed', $line->context['outcome']);
        $this->assertIsFloat($line->context['duration_ms']);
        $this->assertSame(1, $line->context['attempt']);
        $this->assertNotEmpty($line->context['job_id']);
        $this->assertArrayHasKey('queue', $line->context);
    }

    public function test_only_one_line_is_written_per_job(): void
    {
        $this->createUser(User::factory()->admin()->create());

        $this->assertCount(1, $this->lines('job'));
        $this->assertCount(0, $this->lines('job attempt failed'));
    }

    /**
     * The whole point of the pair: a request and the job it dispatched share one
     * id, so one filter in Grafana returns the entire story -- even though the
     * job runs in another container.
     */
    public function test_the_job_inherits_the_request_id_of_whoever_dispatched_it(): void
    {
        $this->withHeader('X-Request-Id', 'id-iz-proxyja');

        $this->createUser(User::factory()->admin()->create());

        $this->assertSame('id-iz-proxyja', $this->line('job')->context['request_id'] ?? Context::get('request_id'));
    }

    /**
     * Anything the job itself logs is stamped with the job's identity without
     * the author of that job doing anything.
     */
    public function test_a_line_written_inside_a_job_carries_the_job_identity(): void
    {
        /**
         * The context is read inside the job and passed into the line itself:
         * a queued closure is serialised, so nothing captured by reference
         * survives the trip, and Context values normally land in Monolog's
         * `extra` rather than in the event this test reads.
         */
        dispatch(fn () => Log::info('radim nesto', Context::all()));

        $written = $this->line('radim nesto')->context;

        $this->assertNotEmpty($written['job']);
        $this->assertSame(1, $written['attempt']);
        $this->assertSame('processed', $this->line('job')->context['outcome']);
    }

    public function test_a_failing_job_is_recorded_as_an_error_with_its_exception(): void
    {
        try {
            dispatch(fn () => throw new RuntimeException('posao je pukao'));
        } catch (RuntimeException) {
            // The sync driver rethrows once the queue events have fired.
        }

        $attempt = $this->line('job attempt failed');

        $this->assertSame('warning', $attempt->level);
        $this->assertSame(RuntimeException::class, $attempt->context['error']['type']);
        $this->assertSame('posao je pukao', $attempt->context['error']['message']);

        $failed = $this->line('job');

        $this->assertSame('error', $failed->level);
        $this->assertSame('failed', $failed->context['outcome']);
        $this->assertSame(RuntimeException::class, $failed->context['error']['type']);
    }

    /**
     * A job started from the console has no request behind it, and must still
     * say what it was.
     */
    public function test_a_job_without_a_request_still_gets_a_line(): void
    {
        dispatch(fn () => null);

        $line = $this->line('job');

        $this->assertSame('processed', $line->context['outcome']);
        $this->assertArrayHasKey('job', $line->context);
    }
}

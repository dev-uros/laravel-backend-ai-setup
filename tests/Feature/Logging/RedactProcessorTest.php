<?php

namespace Tests\Feature\Logging;

use App\Logging\RedactProcessor;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Tests\TestCase;

/**
 * The guard for everything the request logger does not write: a Log:: call made
 * by hand in a service or a job.
 */
class RedactProcessorTest extends TestCase
{
    private function record(array $context = [], array $extra = []): LogRecord
    {
        return new LogRecord(
            new DateTimeImmutable,
            'testing',
            Level::Info,
            'nesto se desilo',
            $context,
            $extra,
        );
    }

    public function test_it_replaces_a_secret_value_but_keeps_the_key(): void
    {
        $record = (new RedactProcessor)($this->record(['user_id' => 5, 'token' => 'tajni-token']));

        $this->assertSame(5, $record->context['user_id']);
        $this->assertSame('[redacted]', $record->context['token']);
    }

    public function test_it_reaches_secrets_nested_at_any_depth(): void
    {
        $record = (new RedactProcessor)($this->record([
            'payload' => ['user' => ['email' => 'uros@fifthguild.com', 'password' => 'tajna']],
        ]));

        $this->assertSame('uros@fifthguild.com', $record->context['payload']['user']['email']);
        $this->assertSame('[redacted]', $record->context['payload']['user']['password']);
    }

    public function test_it_cleans_the_extra_bag_as_well(): void
    {
        $record = (new RedactProcessor)($this->record([], ['access_token' => 'abc']));

        $this->assertSame('[redacted]', $record->extra['access_token']);
    }

    public function test_it_leaves_everything_else_untouched(): void
    {
        $context = ['user_id' => 5, 'route' => 'api/users/{id}', 'count' => 0, 'flag' => false];

        $record = (new RedactProcessor)($this->record($context));

        $this->assertSame($context, $record->context);
    }

    /**
     * Wired the way config/logging.php wires it, so this fails if the channel
     * ever stops carrying the processor.
     */
    public function test_a_channel_configured_like_stderr_redacts_what_a_service_logs(): void
    {
        $channel = Log::build([
            'driver' => 'monolog',
            'handler' => TestHandler::class,
            'processors' => [RedactProcessor::class],
        ]);

        $channel->info('password reset requested', ['user_id' => 5, 'token' => 'tajni-token']);

        /** @var TestHandler $handler */
        $handler = $channel->getLogger()->getHandlers()[0];
        $written = $handler->getRecords()[0];

        $this->assertSame(5, $written->context['user_id']);
        $this->assertSame('[redacted]', $written->context['token']);
    }

    public function test_the_stderr_channel_is_configured_with_it(): void
    {
        $this->assertContains(RedactProcessor::class, config('logging.channels.stderr.processors'));
    }
}

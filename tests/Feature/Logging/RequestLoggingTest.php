<?php

namespace Tests\Feature\Logging;

use App\Logging\RequestContext;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RequestLoggingTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, MessageLogged> */
    private array $logged = [];

    /**
     * Read the lines through the event Laravel fires for every write, rather
     * than through a mocked facade: it exercises the real logger and keeps the
     * assertions about content, not about how it was called.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->logged = [];

        Event::listen(MessageLogged::class, function (MessageLogged $event): void {
            $this->logged[] = $event;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function line(): array
    {
        foreach ($this->logged as $event) {
            if ($event->message === 'request') {
                return $event->context;
            }
        }

        $this->fail('no request line was written');
    }

    private function lineCount(): int
    {
        return count(array_filter($this->logged, fn (MessageLogged $e): bool => $e->message === 'request'));
    }

    public function test_it_writes_one_line_per_request(): void
    {
        $user = User::factory()->create();

        $this->actingAsAdmin(User::factory()->admin()->create())
            ->getJson(route('users.show', $user))
            ->assertOk();

        $this->assertSame(1, $this->lineCount());

        $line = $this->line();

        $this->assertSame('GET', $line['method']);
        $this->assertSame('/api/users/'.$user->id, $line['path']);
        $this->assertSame(200, $line['status']);
        $this->assertIsFloat($line['duration_ms']);
        $this->assertNotEmpty($line['request_id']);
        $this->assertArrayNotHasKey('slow', $line, 'a fast request carries no slow flag');
    }

    /**
     * The pattern, not the path. Without it every id is its own series and
     * "latency by endpoint" cannot be asked at all.
     */
    public function test_it_records_the_route_pattern_and_its_parameters(): void
    {
        $user = User::factory()->create();

        $this->actingAsAdmin(User::factory()->admin()->create())
            ->getJson(route('users.show', $user));

        $line = $this->line();

        $this->assertSame('api/users/{id}', $line['route']);
        $this->assertSame('users.show', $line['route_name']);
        $this->assertSame(['id' => (string) $user->id], $line['route_params']);
    }

    public function test_it_records_who_made_the_request_when_anybody_did(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)->getJson(route('users.index'));

        $line = $this->line();

        $this->assertSame($admin->id, $line['user_id']);
        $this->assertSame('admin', $line['user_role']);
    }

    public function test_an_anonymous_request_carries_no_user(): void
    {
        $this->getJson(route('users.index'))->assertUnauthorized();

        $line = $this->line();

        $this->assertArrayNotHasKey('user_id', $line);
        $this->assertArrayNotHasKey('user_role', $line);
    }

    /**
     * Everything posted to an auth endpoint is either a credential or a token,
     * so the body is dropped whole rather than picked over.
     */
    public function test_it_drops_the_body_of_an_auth_request_entirely(): void
    {
        User::factory()->create(['email' => 'uros.minic@fifthguild.com', 'password' => 'tajna-lozinka']);

        $this->postJson(route('auth.login'), [
            'email' => 'uros.minic@fifthguild.com',
            'password' => 'tajna-lozinka',
        ])->assertOk();

        $line = $this->line();

        $this->assertTrue($line['body_omitted']);
        $this->assertArrayNotHasKey('body', $line);
        $this->assertStringNotContainsString('tajna-lozinka', json_encode($line));
    }

    public function test_it_redacts_a_secret_field_anywhere_else(): void
    {
        Notification::fake();

        $this->actingAsAdmin(User::factory()->admin()->create())
            ->postJson(route('users.store'), [
                'name' => 'Petar',
                'surname' => 'Petrović',
                'email' => 'petar@gmail.com',
                'password' => 'ovo-ne-sme-u-log',
                'nested' => ['token' => 'ni-ovo'],
            ])
            ->assertCreated();

        $line = $this->line();

        $this->assertSame('Petar', $line['body']['name']);
        $this->assertSame('[redacted]', $line['body']['password']);
        $this->assertSame('[redacted]', $line['body']['nested']['token']);
        $this->assertStringNotContainsString('ovo-ne-sme-u-log', json_encode($line));
        $this->assertStringNotContainsString('ni-ovo', json_encode($line));
    }

    /**
     * The response body never goes in. `/auth/login` alone would put a working
     * bearer token into the log stream.
     */
    public function test_the_response_body_is_never_recorded(): void
    {
        $user = User::factory()->create(['password' => 'tajna-lozinka']);

        $token = $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'tajna-lozinka',
        ])->json('data.token');

        $line = $this->line();

        $this->assertArrayNotHasKey('response', $line);
        $this->assertStringNotContainsString($token, json_encode($line));
        $this->assertIsInt($line['bytes'], 'only the size of the response is kept');
    }

    public function test_a_validation_failure_records_the_field_names_but_not_the_values(): void
    {
        $this->actingAsAdmin(User::factory()->admin()->create())
            ->postJson(route('users.store'), ['name' => '', 'surname' => '', 'email' => 'nije-email'])
            ->assertUnprocessable();

        $line = $this->line();

        $this->assertSame('info', strtolower($this->levelOf('request')));
        $this->assertSame(['name', 'surname', 'email'], $line['error']['fields']);
        $this->assertArrayNotHasKey('message', $line['error']);
    }

    public function test_an_unauthorised_request_is_a_warning(): void
    {
        $this->getJson(route('users.index'))->assertUnauthorized();

        $this->assertSame('warning', strtolower($this->levelOf('request')));
    }

    public function test_a_server_error_is_recorded_as_an_error_with_its_exception(): void
    {
        Route::middleware('api')->get('/api/boom', fn () => throw new RuntimeException('sve je puklo'));

        $this->getJson('/api/boom')->assertStatus(Response::HTTP_INTERNAL_SERVER_ERROR);

        $line = $this->line();

        $this->assertSame('error', strtolower($this->levelOf('request')));
        $this->assertSame(500, $line['status']);
        $this->assertSame(RuntimeException::class, $line['error']['type']);
        $this->assertSame('sve je puklo', $line['error']['message']);
    }

    public function test_the_health_check_and_preflight_requests_are_not_logged(): void
    {
        $this->get('/up')->assertOk();
        $this->call('OPTIONS', '/api/users');

        $this->assertSame(0, $this->lineCount());
    }

    public function test_it_reuses_an_incoming_request_id_and_sends_it_back(): void
    {
        $response = $this->withHeader('X-Request-Id', 'id-iz-proxyja')
            ->getJson(route('users.index'));

        $this->assertSame('id-iz-proxyja', $response->headers->get('X-Request-Id'));
        $this->assertSame('id-iz-proxyja', $this->line()['request_id']);
    }

    public function test_it_generates_an_id_when_nobody_sent_one(): void
    {
        $response = $this->getJson(route('users.index'));

        $id = $response->headers->get('X-Request-Id');

        $this->assertNotEmpty($id);
        $this->assertSame($id, $this->line()['request_id']);
    }

    /**
     * The reason the id goes into Context and not just into the line: everything
     * else logged during the request can be tied back to it, queued jobs
     * included.
     */
    public function test_the_id_is_available_to_everything_else_that_logs(): void
    {
        Route::middleware('api')->get('/api/context-check', fn () => response()->json([
            'seen' => Context::get('request_id'),
        ]));

        $this->withHeader('X-Request-Id', 'id-iz-proxyja')
            ->getJson('/api/context-check')
            ->assertOk()
            ->assertJsonPath('seen', 'id-iz-proxyja');
    }

    /**
     * A logger that can take the application down is worse than no logger.
     */
    public function test_a_broken_logger_does_not_break_the_request(): void
    {
        $this->app->bind(RequestContext::class, fn (): RequestContext => new class extends RequestContext
        {
            public function build(Request $request, Response $response, string $requestId, float $startedAt): array
            {
                throw new RuntimeException('logger je pukao');
            }
        });

        $this->getJson(route('users.index'))->assertUnauthorized();

        $this->assertSame(0, $this->lineCount());
        $this->assertNotEmpty(array_filter(
            $this->logged,
            fn (MessageLogged $e): bool => $e->message === 'request log failed',
        ));
    }

    private function levelOf(string $message): string
    {
        foreach ($this->logged as $event) {
            if ($event->message === $message) {
                return $event->level;
            }
        }

        $this->fail("no line with message [{$message}]");
    }
}

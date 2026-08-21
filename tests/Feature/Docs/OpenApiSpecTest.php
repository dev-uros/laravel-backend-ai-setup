<?php

namespace Tests\Feature\Docs;

use App\Models\User;
use App\Models\UserLog;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Holds the generated OpenAPI document against what the endpoints actually
 * return.
 *
 * `/docs/api.json` is generated, so it cannot go stale the way a hand-written
 * document does -- but it can be quietly wrong, and nothing about a green suite
 * would say so. Everything asserted here was wrong at some point: nullable
 * timestamps that never arrive null, a jsonb column documented as a list, and a
 * paginated envelope missing the two keys it always carries.
 */
class OpenApiSpecTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed>|null */
    private static ?array $document = null;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    /**
     * @param  string  $routeName  The endpoint to call.
     * @param  string  $specPath  Its path in the OpenAPI document.
     */
    #[DataProvider('paginatedEndpoints')]
    public function test_the_paginated_envelope_is_documented_exactly_as_it_is_returned(string $routeName, string $specPath): void
    {
        $user = $this->userWithHistory();

        $body = $this->actingAsAdmin($this->admin)
            ->getJson(route($routeName, $user))
            ->assertOk()
            ->json();

        $this->assertNotEmpty($body['data'], 'the fixture produced no rows to compare against');

        $documented = $this->responseSchema($specPath);

        $this->assertEqualsCanonicalizing(array_keys($body), array_keys($documented['properties']));
        $this->assertEqualsCanonicalizing(array_keys($body['links']), array_keys($documented['properties']['links']['properties']));
        $this->assertEqualsCanonicalizing(array_keys($body['meta']), array_keys($documented['properties']['meta']['properties']));
        $this->assertEqualsCanonicalizing(
            array_keys($body['meta']['links'][0]),
            array_keys($documented['properties']['meta']['properties']['links']['items']['properties']),
        );

        /**
         * A LengthAwarePaginator always knows its own first and last page and
         * its own path; only the neighbours can be missing.
         */
        $links = $documented['properties']['links']['properties'];

        $this->assertSame('string', $links['first']['type']);
        $this->assertSame('string', $links['last']['type']);
        $this->assertSame(['string', 'null'], $links['prev']['type']);
        $this->assertSame(['string', 'null'], $links['next']['type']);
        $this->assertSame('string', $documented['properties']['meta']['properties']['path']['type']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function paginatedEndpoints(): array
    {
        return [
            'audit trail' => ['users.logs.index', '/users/{id}/logs'],
            'login history' => ['users.logins.index', '/users/{id}/logins'],
        ];
    }

    public function test_an_unpaginated_list_is_not_documented_as_paginated(): void
    {
        User::factory()->create();

        $body = $this->actingAsAdmin($this->admin)
            ->getJson(route('users.index'))
            ->assertOk()
            ->json();

        $documented = $this->responseSchema('/users');

        $this->assertEqualsCanonicalizing(array_keys($body), array_keys($documented['properties']));
        $this->assertArrayNotHasKey('meta', $documented['properties']);
        $this->assertArrayNotHasKey('links', $documented['properties']);
    }

    public function test_user_timestamps_are_documented_as_always_present(): void
    {
        $user = User::factory()->create();

        $body = $this->actingAsAdmin($this->admin)
            ->getJson(route('users.show', $user))
            ->assertOk()
            ->json('data');

        $properties = $this->schema('UserResource')['properties'];

        foreach (['created_at', 'updated_at'] as $field) {
            $this->assertNotNull($body[$field], "{$field} arrived null");
            $this->assertSame('string', $properties[$field]['type'], "{$field} is documented as nullable");
        }

        /** These two are genuinely nullable and must stay that way. */
        foreach (['deactivated_at', 'email_verified_at'] as $field) {
            $this->assertSame(['string', 'null'], $properties[$field]['type']);
        }
    }

    public function test_a_log_entry_documents_its_diffs_as_objects(): void
    {
        $user = $this->userWithHistory();

        $entry = $this->actingAsAdmin($this->admin)
            ->getJson(route('users.logs.index', $user))
            ->assertOk()
            ->json('data.0');

        $schema = $this->schema('UserLogResource');
        $properties = $schema['properties'];

        $this->assertEqualsCanonicalizing(array_keys($entry), array_keys($properties));

        /**
         * The repository behind this endpoint eager loads the actor, so the key
         * is always there -- including on a system entry, where it is null.
         */
        UserLog::factory()->create(['user_id' => $user->id, 'actor_id' => null]);

        $entries = $this->actingAsAdmin($this->admin)
            ->getJson(route('users.logs.index', $user))
            ->assertOk()
            ->json('data');

        $this->assertContains('actor', $schema['required']);

        foreach ($entries as $index => $each) {
            $this->assertArrayHasKey('actor', $each, "entry {$index} left the actor out");
        }

        $this->assertContains(null, array_column($entries, 'actor'), 'no system entry to check');

        foreach (['old_values', 'new_values'] as $field) {
            $this->assertSame(['object', 'null'], $properties[$field]['type'], "{$field} is documented as a list");
            $this->assertFalse(array_is_list($entry[$field]), "{$field} arrived as a list");
        }
    }

    public function test_a_login_attempt_documents_which_of_its_fields_can_be_missing(): void
    {
        $user = $this->userWithHistory();

        $attempt = $this->actingAsAdmin($this->admin)
            ->getJson(route('users.logins.index', $user))
            ->assertOk()
            ->json('data.0');

        $properties = $this->schema('LoginAttemptResource')['properties'];

        $this->assertEqualsCanonicalizing(array_keys($attempt), array_keys($properties));

        /** `email` and `created_at` are NOT NULL columns and are always written. */
        foreach (['email', 'created_at'] as $field) {
            $this->assertSame('string', $properties[$field]['type'], "{$field} is documented as nullable");
            $this->assertNotNull($attempt[$field], "{$field} arrived null");
        }

        /** These two come off the request and a console sign-in has neither. */
        foreach (['ip_address', 'user_agent'] as $field) {
            $this->assertSame(['string', 'null'], $properties[$field]['type'], "{$field} is documented as always present");
        }
    }

    public function test_a_signed_in_user_is_documented_exactly_as_it_is_returned(): void
    {
        $user = User::factory()->create(['password' => 'tajna-lozinka']);

        $body = $this->postJson(route('auth.login'), ['email' => $user->email, 'password' => 'tajna-lozinka'])
            ->assertOk()
            ->json();

        $documented = $this->responseSchema('/auth/login', 'post');

        $this->assertEqualsCanonicalizing(array_keys($body), array_keys($documented['properties']));
        $this->assertEqualsCanonicalizing(['user', 'token'], array_keys($body['data']));
        $this->assertEqualsCanonicalizing(
            array_keys($body['data']),
            array_keys($this->schema('AuthenticatedUserResource')['properties']),
        );

        /**
         * The nested resource is rendered inline. ApiResponse merging the outer
         * resource's own response is what keeps this out of `data.data`.
         */
        $this->assertArrayNotHasKey('data', $body['data']);
        $this->assertEqualsCanonicalizing(
            array_keys($body['data']['user']),
            array_keys($this->schema('UserResource')['properties']),
        );

        $this->assertIsString($body['data']['token']);
    }

    public function test_a_throttled_route_documents_the_429_it_answers_with(): void
    {
        $documented = $this->document()['paths']['/auth/forgot-password']['post']['responses'][429];

        /**
         * The document describes the production response. With debug on
         * Laravel adds `exception`, `file`, `line` and `trace` to every error
         * it renders, which is a property of the environment and not of the
         * contract.
         */
        config(['app.debug' => false]);

        /** One over `throttle:5,1`. */
        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $response = $this->postJson(route('auth.forgot-password'), ['email' => 'niko@example.test']);
        }

        $response->assertStatus(429);

        $this->assertEqualsCanonicalizing(
            array_keys($response->json()),
            array_keys($documented['content']['application/json']['schema']['properties']),
        );

        $this->assertSame(
            $documented['content']['application/json']['schema']['properties']['message']['examples'],
            [$response->json('message')],
        );

        foreach (array_keys($documented['headers']) as $header) {
            $this->assertTrue($response->headers->has($header), "the response carries no {$header}");
        }
    }

    public function test_a_route_without_a_throttle_promises_no_429(): void
    {
        $this->assertArrayNotHasKey(
            429,
            $this->document()['paths']['/users']['get']['responses'],
            'an unthrottled route documents a status it cannot answer',
        );
    }

    /**
     * A user with one entry in each of the two histories.
     *
     * The sign-in runs before the role change so the request is unauthenticated
     * when it reaches the login endpoint, as a real one would be.
     */
    private function userWithHistory(): User
    {
        $user = User::factory()->create(['password' => 'tajna-lozinka']);

        $this->postJson(route('auth.login'), ['email' => $user->email, 'password' => 'tajna-lozinka'])
            ->assertOk();

        $this->actingAsAdmin($this->admin)
            ->putJson(route('users.role.update', $user), ['role' => 'admin'])
            ->assertOk();

        return $user;
    }

    /**
     * The 200 response schema of a documented endpoint.
     *
     * @return array<string, mixed>
     */
    private function responseSchema(string $path, string $method = 'get'): array
    {
        return $this->document()['paths'][$path][$method]['responses'][200]['content']['application/json']['schema'];
    }

    /**
     * A named component schema.
     *
     * @return array<string, mixed>
     */
    private function schema(string $name): array
    {
        return $this->document()['components']['schemas'][$name];
    }

    /**
     * Generate the document once: it analyses every controller in the
     * application, which is far too slow to repeat per test.
     *
     * @return array<string, mixed>
     */
    private function document(): array
    {
        return self::$document ??= json_decode(
            json_encode(app(Generator::class)(Scramble::getGeneratorConfig('default'))),
            associative: true,
        );
    }
}

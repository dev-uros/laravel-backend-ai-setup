<?php

namespace Tests\Feature\Api\User;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * What each role may reach.
 *
 * The rest of the users tests sign in as whichever role lets them get on with
 * what they are testing; this is the one that pins down the split itself.
 */
class UsersAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The endpoints a plain user must not reach. Named for the policy method
     * each one is authorised by.
     *
     * @return array<string, array{string, string}>
     */
    public static function writeEndpoints(): array
    {
        return [
            'create' => ['postJson', '/api/users'],
            'update' => ['putJson', '/api/users/{id}'],
            'resendActivation' => ['postJson', '/api/users/{id}/resend-activation'],
            'deactivate' => ['postJson', '/api/users/{id}/deactivate'],
            'reactivate' => ['postJson', '/api/users/{id}/reactivate'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function readEndpoints(): array
    {
        return [
            'viewAny' => ['/api/users'],
            'view' => ['/api/users/{id}'],
        ];
    }

    /**
     * `{id}` in the providers above stands for a user that exists. It cannot be
     * hard coded: Postgres sequences are not rolled back with the transaction
     * RefreshDatabase wraps each test in, so ids keep climbing across the suite.
     */
    private function uriFor(string $uri, User $user): string
    {
        return str_replace('{id}', (string) $user->id, $uri);
    }

    #[DataProvider('writeEndpoints')]
    public function test_a_plain_user_is_refused_a_write(string $method, string $uri): void
    {
        $existing = User::factory()->create();

        $this->actingAsUser()
            ->{$method}($this->uriFor($uri, $existing), [
                'name' => 'Petar',
                'surname' => 'Petrović',
                'email' => 'petar@gmail.com',
            ])
            ->assertForbidden();
    }

    #[DataProvider('writeEndpoints')]
    public function test_an_admin_is_allowed_a_write(string $method, string $uri): void
    {
        Notification::fake();
        $existing = User::factory()->create();

        $this->actingAsAdmin(User::factory()->admin()->create())
            ->{$method}($this->uriFor($uri, $existing), [
                'name' => 'Petar',
                'surname' => 'Petrović',
                'email' => 'petar@gmail.com',
            ])
            ->assertSuccessful();
    }

    #[DataProvider('readEndpoints')]
    public function test_a_plain_user_may_read(string $uri): void
    {
        $existing = User::factory()->create();

        $this->actingAsUser()->getJson($this->uriFor($uri, $existing))->assertOk();
    }

    /**
     * 403, not 404 or 422: the caller is known, the request is well formed, and
     * they are simply not allowed. Anything else would be a worse answer.
     */
    public function test_a_refused_write_is_403_and_changes_nothing(): void
    {
        Notification::fake();
        $existing = User::factory()->create();

        $this->actingAsUser()
            ->postJson('/api/users', [
                'name' => 'Petar',
                'surname' => 'Petrović',
                'email' => 'petar@gmail.com',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'petar@gmail.com']);
        $this->assertSame(1, User::query()->count());
        $this->assertDatabaseHas('users', ['id' => $existing->id, 'email' => $existing->email]);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        Notification::assertNothingSent();
    }

    /**
     * The check is on the role in the database, not on anything the caller
     * sends, so a token minted while the account was an administrator stops
     * opening doors the moment the role is taken away.
     */
    public function test_a_demoted_administrator_loses_access_immediately(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('spa')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/users', [
                'name' => 'Petar',
                'surname' => 'Petrović',
                'email' => 'petar@gmail.com',
            ])
            ->assertCreated();

        $admin->forceFill(['role' => UserRole::User])->save();
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/users', [
                'name' => 'Ana',
                'surname' => 'Anić',
                'email' => 'ana@gmail.com',
            ])
            ->assertForbidden();
    }
}

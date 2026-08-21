<?php

namespace Tests\Feature\Api\User;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every users endpoint behind a token, asserted in one place.
 *
 * The other test classes in this directory authenticate in setUp, so none of
 * them would notice a route slipping out of the auth:sanctum group. This one
 * would, and it is the reason the group exists.
 */
class UsersRequireAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string, string}>
     */
    public static function endpoints(): array
    {
        return [
            'index' => ['getJson', '/api/users'],
            'store' => ['postJson', '/api/users'],
            'show' => ['getJson', '/api/users/1'],
            'update' => ['putJson', '/api/users/1'],
            'resend activation' => ['postJson', '/api/users/1/resend-activation'],
            'deactivate' => ['postJson', '/api/users/1/deactivate'],
            'reactivate' => ['postJson', '/api/users/1/reactivate'],
            'change role' => ['putJson', '/api/users/1/role'],
            'logs' => ['getJson', '/api/users/1/logs'],
            'logins' => ['getJson', '/api/users/1/logins'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_it_answers_401_without_a_token(string $method, string $uri): void
    {
        User::factory()->create();

        $this->{$method}($uri, [
            'name' => 'Petar',
            'surname' => 'Petrović',
            'email' => 'petar@gmail.com',
        ])->assertUnauthorized();
    }

    #[DataProvider('endpoints')]
    public function test_it_answers_401_for_a_token_that_does_not_exist(string $method, string $uri): void
    {
        User::factory()->create();

        $this->withHeader('Authorization', 'Bearer 1|nepostojeci-token')
            ->{$method}($uri, [
                'name' => 'Petar',
                'surname' => 'Petrović',
                'email' => 'petar@gmail.com',
            ])->assertUnauthorized();
    }

    /**
     * 401 has to come before the work, not after it. A rejected request that
     * still wrote a row or queued mail would be a hole with a tidy status code
     * on it.
     */
    public function test_a_rejected_request_changes_nothing(): void
    {
        Notification::fake();
        $existing = User::factory()->create();

        $this->postJson('/api/users', [
            'name' => 'Petar',
            'surname' => 'Petrović',
            'email' => 'petar@gmail.com',
        ])->assertUnauthorized();

        $this->postJson("/api/users/{$existing->id}/resend-activation")->assertUnauthorized();

        $this->putJson("/api/users/{$existing->id}", [
            'name' => 'Izmenjeno',
            'surname' => 'Izmenjeno',
            'email' => 'izmenjeno@gmail.com',
        ])->assertUnauthorized();

        $this->assertDatabaseMissing('users', ['email' => 'petar@gmail.com']);
        $this->assertDatabaseHas('users', ['id' => $existing->id, 'email' => $existing->email]);
        $this->assertSame(1, User::query()->count());
        $this->assertDatabaseCount('password_reset_tokens', 0);
        Notification::assertNothingSent();
    }

    /**
     * A token that a logout or a password reset has revoked must be as good as
     * no token at all.
     */
    public function test_it_answers_401_for_a_revoked_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('spa')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/users')
            ->assertOk();

        $user->tokens()->delete();
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/users')
            ->assertUnauthorized();
    }
}

<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A real token rather than Sanctum::actingAs(): that helper attaches a
     * TransientToken, which has no database row to revoke, so it would not
     * exercise what this endpoint does.
     */
    private function tokenFor(User $user): string
    {
        return $user->createToken('spa')->plainTextToken;
    }

    public function test_it_revokes_the_token_the_request_was_made_with(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson(route('auth.logout'))
            ->assertOk()
            ->assertExactJson([
                'message' => 'Uspešno ste odjavljeni.',
                'data' => null,
            ]);

        $this->assertNull(PersonalAccessToken::findToken($token), 'the token must be gone from the database');
    }

    public function test_the_revoked_token_stops_working(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        $this->withHeader('Authorization', 'Bearer '.$token)->postJson(route('auth.logout'))->assertOk();

        /**
         * Both calls share one application instance, and the guard caches the
         * user it resolved for the first one. Without this the second request
         * would pass on that cached user and prove nothing.
         */
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(route('auth.me'))
            ->assertUnauthorized();
    }

    public function test_it_leaves_the_accounts_other_sessions_alone(): void
    {
        $user = User::factory()->create();
        $phone = $this->tokenFor($user);
        $laptop = $this->tokenFor($user);

        $this->withHeader('Authorization', 'Bearer '.$phone)->postJson(route('auth.logout'))->assertOk();

        $this->assertNull(PersonalAccessToken::findToken($phone));
        $this->assertNotNull(PersonalAccessToken::findToken($laptop), 'signing out on one device must not sign the user out on another');
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_it_leaves_other_accounts_alone(): void
    {
        $user = User::factory()->create();
        $someoneElse = User::factory()->create();
        $theirToken = $this->tokenFor($someoneElse);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->postJson(route('auth.logout'))
            ->assertOk();

        $this->assertNotNull(PersonalAccessToken::findToken($theirToken));
    }

    public function test_it_rejects_a_request_without_a_token(): void
    {
        $this->postJson(route('auth.logout'))->assertUnauthorized();
    }

    public function test_it_rejects_an_unknown_token(): void
    {
        $this->withHeader('Authorization', 'Bearer 1|nepostojeci-token')
            ->postJson(route('auth.logout'))
            ->assertUnauthorized();
    }
}

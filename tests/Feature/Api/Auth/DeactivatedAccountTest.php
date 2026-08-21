<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Notifications\PasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * What a switched-off account can still do: nothing.
 *
 * Deactivation revokes the account's tokens, so most of these paths are already
 * closed. These tests cover the ones that are not -- a token that survived, and
 * every way back in that does not need one.
 */
class DeactivatedAccountTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'tajna-lozinka';

    private function deactivatedUser(): User
    {
        return User::factory()->deactivated()->create([
            'email' => 'iskljucen@fifthguild.com',
            'password' => self::PASSWORD,
        ]);
    }

    /**
     * The second lock: a token minted before the account was switched off, or by
     * a code path that forgot to revoke, still gets nowhere.
     */
    public function test_a_surviving_token_is_refused_by_the_middleware(): void
    {
        $user = $this->deactivatedUser();
        $token = $user->createToken('spa')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(route('auth.me'))
            ->assertForbidden()
            ->assertJsonPath('message', 'Nalog je isključen. Obratite se administratoru.');

        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson(route('users.index'))
            ->assertForbidden();
    }

    public function test_logging_in_is_refused_with_403_and_a_plain_reason(): void
    {
        $user = $this->deactivatedUser();

        $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])
            ->assertForbidden()
            ->assertJsonPath('message', 'Nalog je isključen. Obratite se administratoru.');

        $this->assertSame(0, $user->tokens()->count(), 'a refused login must not mint a token');
    }

    /**
     * The reason is only given once the password proves the account is theirs.
     * A wrong password answers exactly as it would for any other account, or
     * this endpoint would tell an attacker which accounts are switched off.
     */
    public function test_a_wrong_password_still_answers_401(): void
    {
        $user = $this->deactivatedUser();

        $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'pogresna-lozinka',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Neispravni podaci za prijavu.');
    }

    public function test_forgot_password_answers_identically_and_sends_nothing(): void
    {
        Notification::fake();
        $user = $this->deactivatedUser();

        $deactivated = $this->postJson(route('auth.forgot-password'), ['email' => $user->email]);
        $unknown = $this->postJson(route('auth.forgot-password'), ['email' => 'nepostojeci@fifthguild.com']);

        $deactivated->assertOk();
        $this->assertSame($unknown->getContent(), $deactivated->getContent());
        $this->assertSame($unknown->getStatusCode(), $deactivated->getStatusCode());

        Notification::assertNothingSent();
    }

    /**
     * 422 and the token's own message, not 403: this check runs before the token
     * is verified, so a distinct answer would let anyone holding nothing but an
     * email address discover which accounts are switched off.
     */
    public function test_resetting_the_password_is_refused_as_an_invalid_token(): void
    {
        $user = $this->deactivatedUser();
        $token = Password::broker()->createToken($user);

        $this->postJson(route('auth.reset-password'), [
            'email' => $user->email,
            'token' => $token,
            'password' => 'Nova-Lozinka1!',
            'password_confirmation' => 'Nova-Lozinka1!',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Link za promenu lozinke nije važeći ili je istekao. Zatražite novi.');

        $this->assertTrue(
            Password::broker()->tokenExists($user, $token),
            'a refused attempt must not spend the token',
        );
    }

    public function test_activating_the_account_is_refused_as_an_invalid_token(): void
    {
        $user = User::factory()->deactivated()->unverified()->create([
            'email' => 'iskljucen@fifthguild.com',
        ]);
        $token = Password::broker()->createToken($user);

        $this->postJson(route('auth.activate-account'), [
            'email' => $user->email,
            'token' => $token,
            'password' => 'Nova-Lozinka1!',
            'password_confirmation' => 'Nova-Lozinka1!',
        ])->assertUnprocessable();

        $this->assertNull($user->refresh()->email_verified_at);
        $this->assertTrue(
            Password::broker()->tokenExists($user, $token),
            'a refused attempt must not spend the token',
        );
    }

    public function test_an_active_account_is_unaffected_by_any_of_this(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => self::PASSWORD]);

        $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => self::PASSWORD,
        ])->assertOk();

        $this->postJson(route('auth.forgot-password'), ['email' => $user->email])->assertOk();

        Notification::assertSentTo($user, PasswordResetNotification::class);
    }
}

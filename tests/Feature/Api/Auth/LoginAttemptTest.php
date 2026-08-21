<?php

namespace Tests\Feature\Api\Auth;

use App\Enums\LoginOutcome;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * What every attempt to sign in leaves behind.
 */
class LoginAttemptTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'tajna-lozinka';

    private function user(array $attributes = []): User
    {
        return User::factory()->create([
            'email' => 'uros.minic@fifthguild.com',
            'password' => self::PASSWORD,
            ...$attributes,
        ]);
    }

    private function attempt(string $email, string $password = self::PASSWORD): TestResponse
    {
        return $this->postJson(route('auth.login'), ['email' => $email, 'password' => $password]);
    }

    public function test_a_successful_login_is_recorded_against_the_account(): void
    {
        $user = $this->user();

        $this->attempt($user->email)->assertOk();

        $entry = LoginAttempt::query()->sole();

        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame($user->email, $entry->email);
        $this->assertSame(LoginOutcome::Succeeded, $entry->outcome);
        $this->assertSame('127.0.0.1', $entry->ip_address);
        $this->assertNotNull($entry->created_at);
    }

    public function test_a_wrong_password_is_recorded_as_a_failure(): void
    {
        $user = $this->user();

        $this->attempt($user->email, 'pogresna-lozinka')->assertUnauthorized();

        $entry = LoginAttempt::query()->sole();

        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame(LoginOutcome::InvalidCredentials, $entry->outcome);
    }

    /**
     * An address nobody uses still leaves a row -- with no account attached,
     * because there is none. That row is the only thing that makes a sweep
     * across invented addresses visible.
     */
    public function test_an_unknown_address_is_recorded_without_a_user(): void
    {
        $this->attempt('niko@fifthguild.com')->assertUnauthorized();

        $entry = LoginAttempt::query()->sole();

        $this->assertNull($entry->user_id);
        $this->assertSame('niko@fifthguild.com', $entry->email);
        $this->assertSame(LoginOutcome::InvalidCredentials, $entry->outcome);
    }

    /**
     * The table must not answer what the endpoint refuses to: both failures are
     * the same outcome, so nobody can tell a wrong password from an address that
     * does not exist by reading it.
     */
    public function test_both_kinds_of_failure_are_recorded_identically(): void
    {
        $user = $this->user();

        $this->attempt($user->email, 'pogresna-lozinka')->assertUnauthorized();
        $this->attempt('niko@fifthguild.com')->assertUnauthorized();

        $outcomes = LoginAttempt::query()->pluck('outcome')->unique()->values();

        $this->assertCount(1, $outcomes);
        $this->assertSame(LoginOutcome::InvalidCredentials, $outcomes->first());
    }

    public function test_a_deactivated_account_is_recorded_with_its_own_outcome(): void
    {
        $user = $this->user(['deactivated_at' => now()]);

        $this->attempt($user->email)->assertForbidden();

        $entry = LoginAttempt::query()->sole();

        $this->assertSame($user->id, $entry->user_id);
        $this->assertSame(LoginOutcome::AccountDeactivated, $entry->outcome);
    }

    /**
     * That outcome is only reachable once the password has been checked, so a
     * wrong password on a switched-off account must not reveal its state here
     * either.
     */
    public function test_a_wrong_password_on_a_deactivated_account_says_nothing_about_it(): void
    {
        $user = $this->user(['deactivated_at' => now()]);

        $this->attempt($user->email, 'pogresna-lozinka')->assertUnauthorized();

        $this->assertSame(LoginOutcome::InvalidCredentials, LoginAttempt::query()->sole()->outcome);
    }

    public function test_the_password_is_never_stored(): void
    {
        $user = $this->user();

        $this->attempt($user->email)->assertOk();
        $this->attempt($user->email, 'pogresna-lozinka')->assertUnauthorized();

        $serialised = LoginAttempt::query()->get()->toJson();

        $this->assertStringNotContainsString(self::PASSWORD, $serialised);
        $this->assertStringNotContainsString('pogresna-lozinka', $serialised);
        $this->assertStringNotContainsString('$2y$', $serialised);
    }

    /**
     * Every branch out of the service writes exactly one row, which is also what
     * keeps a failed login costing the same whether or not the address exists.
     */
    public function test_every_attempt_writes_exactly_one_row(): void
    {
        $user = $this->user();

        $this->attempt($user->email)->assertOk();
        $this->attempt($user->email, 'pogresno')->assertUnauthorized();
        $this->attempt('niko@fifthguild.com')->assertUnauthorized();

        $this->assertSame(3, LoginAttempt::query()->count());
    }

    public function test_a_malformed_address_is_rejected_before_anything_is_recorded(): void
    {
        $this->attempt('nije-email')->assertUnprocessable();

        $this->assertSame(0, LoginAttempt::query()->count(), 'validation runs before the service');
    }

    public function test_old_attempts_are_prunable(): void
    {
        $user = $this->user();

        LoginAttempt::factory()->create(['user_id' => $user->id, 'created_at' => now()->subDays(LoginAttempt::RETENTION_DAYS + 1)]);
        $kept = LoginAttempt::factory()->create(['user_id' => $user->id, 'created_at' => now()->subDay()]);

        $this->artisan('model:prune', ['--model' => [LoginAttempt::class]])->assertSuccessful();

        $this->assertSame(1, LoginAttempt::query()->count());
        $this->assertTrue(LoginAttempt::query()->sole()->is($kept));
    }
}

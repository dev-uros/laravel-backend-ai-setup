<?php

namespace Tests\Feature\Console;

use App\Enums\UserLogEvent;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserLog;
use App\Notifications\AccountCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

/**
 * The only way to create the account that has to exist before anybody can call
 * POST /api/users -- see CreateAdminCommand for why the seeder cannot do it in
 * production.
 *
 * What is asserted here is mostly that it takes the ordinary path: the same
 * service, the same emailed link, the same two entries in the audit trail. A
 * command that created a user by talking to Eloquent directly would work and
 * would leave no trail, which is the failure worth guarding against.
 */
class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'uros.minic@fifthguild.com';

    private function createAdmin(array $arguments = []): PendingCommand
    {
        return $this->artisan('admin:create', [
            'email' => self::EMAIL,
            '--name' => 'Uroš',
            '--surname' => 'Minić',
            ...$arguments,
        ]);
    }

    public function test_it_creates_an_administrator(): void
    {
        Notification::fake();

        $this->createAdmin()->assertSuccessful();

        $user = User::query()->sole();

        $this->assertSame('Uroš', $user->name);
        $this->assertSame('Minić', $user->surname);
        $this->assertSame(self::EMAIL, $user->email);
        $this->assertSame(UserRole::Admin, $user->role);
    }

    /**
     * Nobody, including whoever ran the command, ever learns this password --
     * the account is reached through the activation link instead.
     */
    public function test_the_account_starts_with_a_password_nobody_knows(): void
    {
        Notification::fake();

        $this->createAdmin()->assertSuccessful();

        $user = User::query()->sole();

        $this->assertNull($user->email_verified_at);
        $this->assertFalse(Hash::check('password', $user->password));
    }

    public function test_it_emails_the_activation_link(): void
    {
        Notification::fake();

        $this->createAdmin()->assertSuccessful();

        Notification::assertSentTo(User::query()->sole(), AccountCreatedNotification::class);
        Notification::assertCount(1);
    }

    /**
     * Attributed to nobody, because nobody was signed in. A null actor is a
     * statement -- "the system did this" -- not a gap.
     */
    public function test_it_leaves_the_same_trail_as_the_endpoint_with_no_actor(): void
    {
        Notification::fake();

        $this->createAdmin()->assertSuccessful();

        $entries = UserLog::query()->orderBy('id')->get();

        $this->assertSame(
            [UserLogEvent::Created, UserLogEvent::ActivationLinkSent],
            $entries->pluck('event')->all(),
        );
        $this->assertTrue($entries->every(fn (UserLog $entry): bool => $entry->actor_id === null));
        $this->assertSame(UserRole::Admin->value, $entries->first()->new_values['role']);
    }

    public function test_it_refuses_an_address_that_already_exists(): void
    {
        Notification::fake();

        User::factory()->create(['email' => self::EMAIL]);

        $this->createAdmin()->assertFailed();

        $this->assertSame(1, User::query()->count());
        Notification::assertNothingSent();
    }

    public function test_it_refuses_an_address_that_is_not_an_address(): void
    {
        Notification::fake();

        $this->artisan('admin:create', [
            'email' => 'not-an-email',
            '--name' => 'Uroš',
            '--surname' => 'Minić',
        ])->assertFailed();

        $this->assertSame(0, User::query()->count());
        Notification::assertNothingSent();
    }

    /**
     * The escape hatch for a server whose SMTP does not work yet. It prints a
     * link into the frontend, not into this API.
     */
    public function test_show_link_prints_a_frontend_activation_url(): void
    {
        Notification::fake();

        $this->createAdmin(['--show-link' => true])
            ->expectsOutputToContain(config('app.frontend_url').'/auth/activate-account')
            ->assertSuccessful();

        // The link is only useful if the token behind it was actually minted.
        // Asserted here rather than by matching the query string in the output,
        // which the console is free to wrap.
        $this->assertDatabaseHas('password_reset_tokens', ['email' => self::EMAIL]);
    }

    public function test_without_show_link_no_url_is_printed(): void
    {
        Notification::fake();

        $this->createAdmin()
            ->doesntExpectOutputToContain('/auth/activate-account')
            ->assertSuccessful();
    }
}

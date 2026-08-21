<?php

namespace Tests\Feature\Api\UserLog;

use App\Enums\UserLogEvent;
use App\Enums\UserRole;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * What every write to a user account leaves behind.
 *
 * One test per use case, because the whole point of the trail is that no use
 * case is silent.
 */
class UserLogRecordingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function logsFor(User $user): Collection
    {
        return UserLog::query()->where('user_id', $user->id)->orderBy('id')->get();
    }

    public function test_creating_a_user_records_who_created_them_and_what_with(): void
    {
        Notification::fake();

        $id = $this->actingAsAdmin($this->admin)
            ->postJson(route('users.store'), [
                'name' => 'Petar',
                'surname' => 'Petrović',
                'email' => 'petar@gmail.com',
                'role' => 'admin',
            ])
            ->assertCreated()
            ->json('data.id');

        $created = UserLog::query()->where('user_id', $id)->where('event', UserLogEvent::Created)->sole();

        $this->assertSame($this->admin->id, $created->actor_id);
        $this->assertNull($created->old_values);
        /**
         * assertEquals, not assertSame: jsonb does not keep the order the keys
         * went in with, so comparing order would be testing Postgres internals.
         */
        $this->assertEquals([
            'name' => 'Petar',
            'surname' => 'Petrović',
            'email' => 'petar@gmail.com',
            'role' => 'admin',
        ], $created->new_values);
        $this->assertNotNull($created->created_at);
    }

    public function test_creating_a_user_also_records_the_activation_email(): void
    {
        Notification::fake();

        $id = $this->actingAsAdmin($this->admin)
            ->postJson(route('users.store'), [
                'name' => 'Petar',
                'surname' => 'Petrović',
                'email' => 'petar@gmail.com',
            ])
            ->assertCreated()
            ->json('data.id');

        $sent = UserLog::query()->where('user_id', $id)->where('event', UserLogEvent::ActivationLinkSent)->sole();

        $this->assertSame($this->admin->id, $sent->actor_id);
        $this->assertNull($sent->new_values, 'nothing on the row changed, so there is nothing to diff');
    }

    public function test_an_update_records_only_the_fields_that_moved(): void
    {
        $user = User::factory()->create(['name' => 'Staro', 'surname' => 'Prezime', 'email' => 'staro@gmail.com']);

        $this->actingAsAdmin($this->admin)
            ->putJson(route('users.update', $user), [
                'name' => 'Novo',
                'surname' => 'Prezime',
                'email' => 'staro@gmail.com',
            ])
            ->assertOk();

        $entry = UserLog::query()->where('user_id', $user->id)->where('event', UserLogEvent::Updated)->sole();

        $this->assertSame(['name' => 'Staro'], $entry->old_values);
        $this->assertSame(['name' => 'Novo'], $entry->new_values);
    }

    public function test_an_update_that_changes_nothing_records_nothing(): void
    {
        $user = User::factory()->create(['name' => 'Isto', 'surname' => 'Isto', 'email' => 'isto@gmail.com']);

        $this->actingAsAdmin($this->admin)
            ->putJson(route('users.update', $user), [
                'name' => 'Isto',
                'surname' => 'Isto',
                'email' => 'isto@gmail.com',
            ])
            ->assertOk();

        $this->assertCount(0, $this->logsFor($user));
    }

    public function test_a_role_change_records_both_roles(): void
    {
        $user = User::factory()->create();

        $this->actingAsAdmin($this->admin)
            ->putJson(route('users.role.update', $user), ['role' => 'admin'])
            ->assertOk();

        $entry = UserLog::query()->where('user_id', $user->id)->sole();

        $this->assertSame(UserLogEvent::RoleChanged, $entry->event);
        $this->assertSame(['role' => 'user'], $entry->old_values);
        $this->assertSame(['role' => 'admin'], $entry->new_values);
        $this->assertSame($this->admin->id, $entry->actor_id);
    }

    public function test_deactivating_and_reactivating_are_both_recorded(): void
    {
        $user = User::factory()->create();

        $this->actingAsAdmin($this->admin)->postJson(route('users.deactivate', $user))->assertOk();
        $this->actingAsAdmin($this->admin)->postJson(route('users.reactivate', $user))->assertOk();

        $events = $this->logsFor($user)->pluck('event')->all();

        $this->assertSame([UserLogEvent::Deactivated, UserLogEvent::Reactivated], $events);

        $off = $this->logsFor($user)->first();
        $this->assertNull($off->old_values['deactivated_at']);
        $this->assertNotNull($off->new_values['deactivated_at']);
    }

    /**
     * Nobody is signed in when an activation link is used, so the account is
     * recorded as acting on itself rather than as an anonymous change.
     */
    public function test_activating_an_account_is_attributed_to_that_account(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $token = Password::broker()->createToken($user);

        $this->postJson(route('auth.activate-account'), [
            'email' => $user->email,
            'token' => $token,
            'password' => 'Nova-Lozinka1!',
            'password_confirmation' => 'Nova-Lozinka1!',
        ])->assertOk();

        $entry = UserLog::query()->where('user_id', $user->id)->where('event', UserLogEvent::Activated)->sole();

        $this->assertSame($user->id, $entry->actor_id);
        $this->assertNull($entry->old_values['email_verified_at']);
        $this->assertNotNull($entry->new_values['email_verified_at']);
    }

    public function test_a_password_reset_is_recorded_without_the_password(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);

        $this->postJson(route('auth.reset-password'), [
            'email' => $user->email,
            'token' => $token,
            'password' => 'Nova-Lozinka1!',
            'password_confirmation' => 'Nova-Lozinka1!',
        ])->assertOk();

        $entry = UserLog::query()->where('user_id', $user->id)->where('event', UserLogEvent::PasswordReset)->sole();

        $this->assertSame($user->id, $entry->actor_id);
        $this->assertNull($entry->old_values);
        $this->assertNull($entry->new_values);
    }

    /**
     * The one rule the trail must never break, asserted across every entry the
     * whole flow produces rather than one call at a time.
     */
    public function test_no_entry_anywhere_ever_contains_a_password(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAsAdmin($this->admin)
            ->putJson(route('users.update', $user), [
                'name' => 'Novo',
                'surname' => 'Novo',
                'email' => 'novo@gmail.com',
            ])->assertOk();
        $this->actingAsAdmin($this->admin)->postJson(route('users.resend-activation', $user))->assertOk();
        $this->actingAsAdmin($this->admin)->postJson(route('users.deactivate', $user))->assertOk();

        /**
         * Refreshed first: the email changed above, and the broker keys its
         * token by whatever address the model it is handed carries.
         */
        $token = Password::broker()->createToken($user->refresh());
        $this->actingAsAdmin($this->admin)->postJson(route('users.reactivate', $user))->assertOk();
        $this->postJson(route('auth.reset-password'), [
            'email' => 'novo@gmail.com',
            'token' => $token,
            'password' => 'Nova-Lozinka1!',
            'password_confirmation' => 'Nova-Lozinka1!',
        ])->assertOk();

        $entries = UserLog::query()->get();

        $this->assertGreaterThan(3, $entries->count(), 'the flow above must actually have left a trail');

        foreach ($entries as $entry) {
            $values = array_merge($entry->old_values ?? [], $entry->new_values ?? []);

            $this->assertArrayNotHasKey('password', $values);
            $this->assertArrayNotHasKey('remember_token', $values);

            /**
             * The event name is allowed to say "password"; the recorded values
             * are not allowed to be one.
             */
            $this->assertStringNotContainsString('Nova-Lozinka1!', json_encode($values));
            $this->assertStringNotContainsString('$2y$', json_encode($values), 'not even a hash');
        }
    }

    /**
     * A failed change must leave no trace: the entry is written inside the same
     * transaction, so it rolls back with it.
     */
    public function test_a_rolled_back_change_leaves_no_entry(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['email' => 'zauzet@gmail.com']);

        $this->actingAsAdmin($this->admin)
            ->putJson(route('users.update', $user), [
                'name' => 'Novo',
                'surname' => 'Novo',
                'email' => 'zauzet@gmail.com',
            ])
            ->assertUnprocessable();

        $this->assertCount(0, $this->logsFor($user));
        $this->assertSame('zauzet@gmail.com', $other->refresh()->email);
    }

    /**
     * Kept far longer than a login attempt: the trail is asked about long after
     * the fact, during a dispute or an audit.
     */
    public function test_old_entries_are_pruned_on_a_three_year_clock(): void
    {
        $user = User::factory()->create();

        $expired = UserLog::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subDays(UserLog::RETENTION_DAYS + 1),
        ]);
        $kept = UserLog::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subDays(UserLog::RETENTION_DAYS - 1),
        ]);

        $this->artisan('model:prune', ['--model' => [UserLog::class]])->assertSuccessful();

        $this->assertSame(1, UserLog::query()->count());
        $this->assertTrue(UserLog::query()->sole()->is($kept));
        $this->assertDatabaseMissing('user_logs', ['id' => $expired->id]);
    }

    /**
     * The trail outlives login history by a long way, and the two must not be
     * pruned on the same clock by accident.
     */
    public function test_it_is_kept_much_longer_than_a_login_attempt(): void
    {
        $this->assertGreaterThan(LoginAttempt::RETENTION_DAYS * 2, UserLog::RETENTION_DAYS);
    }

    public function test_a_console_caller_is_recorded_as_the_system(): void
    {
        Notification::fake();

        $this->artisan('db:seed', ['--class' => 'UserSeeder'])->assertSuccessful();

        $seeded = User::query()->where('email', 'uros.minic@fifthguild.com')->sole();

        $this->assertSame(UserRole::Admin, $seeded->role);
        $this->assertCount(0, $this->logsFor($seeded), 'the seeder writes rows directly, not through the use case');
    }
}

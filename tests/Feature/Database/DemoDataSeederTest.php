<?php

namespace Tests\Feature\Database;

use App\Enums\LoginOutcome;
use App\Enums\UserLogEvent;
use App\Enums\UserRole;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Models\UserLog;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The demo data is what the frontend is built against, so it has to keep
 * working. It also goes in through the real services, which makes this a
 * standing check that those signatures still line up.
 */
class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Named seedDemo, not seed: Laravel's TestCase already has a public seed().
     */
    private function seedDemo(): void
    {
        /**
         * The seeder puts real avatars on the `public` disk. Faked here, or a
         * test run would leave files in storage/app/public that nothing removes.
         */
        Storage::fake(User::AVATAR_DISK);

        $this->seed(UserSeeder::class);
        $this->seed(DemoDataSeeder::class);
    }

    public function test_it_creates_accounts_in_every_state_the_screens_have_to_show(): void
    {
        $this->seedDemo();

        $users = User::query()->get();

        $this->assertGreaterThanOrEqual(7, $users->count());
        $this->assertGreaterThanOrEqual(2, $users->where('role', UserRole::Admin)->count());
        $this->assertTrue($users->contains(fn (User $u): bool => $u->email_verified_at === null), 'one account never activated');
        $this->assertTrue($users->contains(fn (User $u): bool => ! $u->isActive()), 'one account switched off');
    }

    /**
     * Some accounts have a picture and some do not: the frontend has to render
     * both, and the placeholder is as much a state as the image is.
     */
    public function test_some_accounts_have_an_avatar_and_some_do_not(): void
    {
        $this->seedDemo();

        $users = User::query()->get();
        $withAvatar = $users->filter(fn (User $u): bool => $u->avatar_path !== null);

        $this->assertGreaterThanOrEqual(2, $withAvatar->count(), 'nothing to render an avatar from');
        $this->assertGreaterThanOrEqual(2, $users->count() - $withAvatar->count(), 'no account left for the placeholder');

        foreach ($withAvatar as $user) {
            Storage::disk(User::AVATAR_DISK)->assertExists($user->avatar_path);
            $this->assertStringEndsWith('.webp', $user->avatar_path);
        }

        /**
         * The upload went through UpdateUserAvatarService, so the trail knows
         * about it -- by the account itself in most cases, by an administrator
         * in at least one.
         */
        $entries = UserLog::query()->where('event', UserLogEvent::AvatarUpdated)->get();

        $this->assertCount($withAvatar->count(), $entries);
        $this->assertTrue($entries->contains(fn (UserLog $e): bool => $e->actor_id === $e->user_id), 'nobody set their own');
        $this->assertTrue($entries->contains(fn (UserLog $e): bool => $e->actor_id !== $e->user_id), 'no admin-set avatar');
    }

    /**
     * docs/frontend-integration.md prints this table for whoever builds the SPA,
     * and a table nothing asserts drifts the first time the seeder is touched.
     * The rows here are that table: change one and change the other.
     *
     * @return array<string, array{string, UserRole, bool, bool, bool}>
     */
    public static function documentedAccounts(): array
    {
        // email, role, has an avatar, is active, has activated
        return [
            'the seeded administrator' => ['uros.minic@fifthguild.com', UserRole::Admin, false, true, true],
            'the everyday administrator' => ['milica.jovanovic@fifthguild.com', UserRole::Admin, true, true, true],
            'promoted out of user' => ['teodora.markovic@fifthguild.com', UserRole::Admin, true, true, true],
            'renamed, with a new address' => ['marko.petrovic-ilic@fifthguild.com', UserRole::User, true, true, true],
            'reset their own password' => ['ana.nikolic@fifthguild.com', UserRole::User, false, true, true],
            'nothing remarkable' => ['stefan.djordjevic@fifthguild.com', UserRole::User, false, true, true],
            'never activated' => ['jovana.stankovic@fifthguild.com', UserRole::User, false, true, false],
            'switched off' => ['nikola.ilic@fifthguild.com', UserRole::User, true, false, true],
        ];
    }

    #[DataProvider('documentedAccounts')]
    public function test_the_documented_demo_accounts_exist_in_the_documented_state(
        string $email,
        UserRole $role,
        bool $hasAvatar,
        bool $isActive,
        bool $hasActivated,
    ): void {
        $this->seedDemo();

        $user = User::query()->where('email', $email)->sole();

        $this->assertSame($role, $user->role);
        $this->assertSame($hasAvatar, $user->avatar_path !== null, 'avatar state differs from the documented one');
        $this->assertSame($isActive, $user->isActive(), 'active state differs from the documented one');
        $this->assertSame($hasActivated, $user->email_verified_at !== null, 'activation state differs from the documented one');
    }

    /**
     * The documented table is the whole list, not a sample of it -- an extra
     * account nobody wrote down is as much a drift as a missing one.
     */
    public function test_the_documented_table_is_the_complete_list(): void
    {
        $this->seedDemo();

        $this->assertEqualsCanonicalizing(
            array_column(self::documentedAccounts(), 0),
            User::query()->pluck('email')->all(),
        );
    }

    public function test_every_account_signs_in_with_the_documented_password(): void
    {
        $this->seedDemo();

        $user = User::query()->where('email', 'ana.nikolic@fifthguild.com')->sole();

        $this->postJson(route('auth.login'), ['email' => $user->email, 'password' => 'password'])
            ->assertOk();
    }

    public function test_the_trail_is_spread_over_weeks_rather_than_all_at_once(): void
    {
        $this->seedDemo();

        $logs = UserLog::query()->get();

        $this->assertGreaterThanOrEqual(20, $logs->count());
        $this->assertTrue(
            $logs->min('created_at')->lessThan(now()->subDays(30)),
            'the oldest entry should be over a month old',
        );
        $this->assertTrue(
            $logs->max('created_at')->lessThanOrEqualTo(now()),
            'nothing should be dated in the future',
        );
    }

    public function test_it_produces_login_history_including_failures_and_unknown_addresses(): void
    {
        $this->seedDemo();

        $attempts = LoginAttempt::query()->get();

        $this->assertGreaterThanOrEqual(50, $attempts->count());
        $this->assertTrue($attempts->contains('outcome', LoginOutcome::Succeeded));
        $this->assertTrue($attempts->contains('outcome', LoginOutcome::InvalidCredentials));
        $this->assertTrue($attempts->contains('outcome', LoginOutcome::AccountDeactivated));
        $this->assertGreaterThan(0, $attempts->whereNull('user_id')->count(), 'attempts on addresses nobody uses');
    }

    public function test_the_entries_are_attributed_to_somebody(): void
    {
        $this->seedDemo();

        $created = UserLog::query()->where('event', UserLogEvent::Created)->get();

        $this->assertNotEmpty($created);
        $this->assertTrue($created->every(fn (UserLog $log): bool => $log->actor_id !== null));
    }

    public function test_running_it_twice_changes_nothing(): void
    {
        $this->seedDemo();

        $before = [User::query()->count(), UserLog::query()->count(), LoginAttempt::query()->count()];

        $this->seed(DemoDataSeeder::class);

        $this->assertSame($before, [User::query()->count(), UserLog::query()->count(), LoginAttempt::query()->count()]);
    }
}

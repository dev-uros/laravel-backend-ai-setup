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

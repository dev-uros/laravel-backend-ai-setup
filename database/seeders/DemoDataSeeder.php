<?php

namespace Database\Seeders;

use App\Data\ActorData;
use App\Enums\LoginOutcome;
use App\Enums\UserLogEvent;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Auth\RecordLoginAttemptService;
use App\Services\UserLog\RecordUserLogService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * A believable few weeks of history, so the frontend has something to render.
 *
 * Deliberately not part of DatabaseSeeder: a fresh install gets one real
 * administrator and nothing else. Run it when you want the screens populated:
 *
 *     make demo
 *
 * The entries go in through the same services the application uses, so their
 * shape cannot drift from the real thing. Only the clock is faked -- each write
 * happens inside Carbon::setTestNow(), which is what gives the trail dates
 * spread over the past weeks instead of all of them landing at "just now".
 */
class DemoDataSeeder extends Seeder
{
    /**
     * Every demo account uses this, including the ones that are switched off, so
     * you can sign in as any of them and see the application through their eyes.
     */
    private const PASSWORD = 'password';

    /**
     * The account whose presence means this has already run.
     */
    private const MARKER_EMAIL = 'milica.jovanovic@fifthguild.com';

    private RecordUserLogService $recordLog;

    private RecordLoginAttemptService $recordAttempt;

    private User $admin;

    public function run(): void
    {
        if (User::query()->where('email', self::MARKER_EMAIL)->exists()) {
            $this->command?->warn('Demo data is already there -- nothing to do.');

            return;
        }

        $this->recordLog = app(RecordUserLogService::class);
        $this->recordAttempt = app(RecordLoginAttemptService::class);

        $this->admin = User::query()->where('role', UserRole::Admin)->orderBy('id')->first()
            ?? throw new \RuntimeException('Run UserSeeder first: the trail needs an administrator to attribute changes to.');

        $this->everydayAdmin();
        $this->plainUsers();
        $this->neverActivated();
        $this->switchedOff();
        $this->promoted();
        $this->strangersAtTheDoor();

        $this->command?->info('Demo data seeded. Every account signs in with the password "'.self::PASSWORD.'".');
    }

    /**
     * A second administrator, created a month and a half ago, who has been using
     * the application ever since.
     */
    private function everydayAdmin(): void
    {
        $user = $this->createUser('Milica', 'Jovanović', self::MARKER_EMAIL, 44, UserRole::Admin);

        $this->activated($user, 44);
        $this->signsInRegularly($user, from: 43, times: 22);
    }

    private function plainUsers(): void
    {
        $marko = $this->createUser('Marko', 'Petrović', 'marko.petrovic@fifthguild.com', 38);
        $this->activated($marko, 37);
        $this->signsInRegularly($marko, from: 36, times: 14);

        /** Changed their surname after getting married, and their address with it. */
        $this->at($this->moment(12, 10, 15), function () use ($marko): void {
            $before = $marko->only(['surname', 'email']);
            $marko->forceFill(['surname' => 'Petrović-Ilić', 'email' => 'marko.petrovic-ilic@fifthguild.com'])->save();

            $this->recordLog->handle($marko, UserLogEvent::Updated, $this->actor(), $before, [
                'surname' => 'Petrović-Ilić',
                'email' => 'marko.petrovic-ilic@fifthguild.com',
            ]);
        });

        $ana = $this->createUser('Ana', 'Nikolić', 'ana.nikolic@fifthguild.com', 30);
        $this->activated($ana, 30);
        $this->signsInRegularly($ana, from: 29, times: 9);

        /** Forgot their password a fortnight ago and set a new one. */
        $this->at($this->moment(14, 9, 5), function () use ($ana): void {
            $this->recordAttempt->handle($ana, $ana->email, LoginOutcome::InvalidCredentials, $this->theirDevice());
            $this->recordAttempt->handle($ana, $ana->email, LoginOutcome::InvalidCredentials, $this->theirDevice());
        });

        $this->at($this->moment(14, 9, 20), function () use ($ana): void {
            $this->recordLog->handle($ana, UserLogEvent::PasswordReset, $this->theirDevice()->actingAs($ana->id));
            $this->recordAttempt->handle($ana, $ana->email, LoginOutcome::Succeeded, $this->theirDevice());
        });

        $stefan = $this->createUser('Stefan', 'Đorđević', 'stefan.djordjevic@fifthguild.com', 21);
        $this->activated($stefan, 19);
        $this->signsInRegularly($stefan, from: 18, times: 6);
    }

    /**
     * Invited three weeks ago, reminded twice, still has not set a password.
     */
    private function neverActivated(): void
    {
        $user = $this->createUser('Jovana', 'Stanković', 'jovana.stankovic@fifthguild.com', 23);

        $user->forceFill(['email_verified_at' => null])->save();

        foreach ([16, 9] as $daysAgo) {
            $this->at($this->moment($daysAgo, 11, 30), function () use ($user): void {
                $this->recordLog->handle($user, UserLogEvent::ActivationLinkSent, $this->actor());
            });
        }
    }

    /**
     * Left the company: switched off a week ago, and the attempt they made after
     * that is in the login history.
     */
    private function switchedOff(): void
    {
        $user = $this->createUser('Nikola', 'Ilić', 'nikola.ilic@fifthguild.com', 40);

        $this->activated($user, 40);
        $this->signsInRegularly($user, from: 39, times: 11, until: 8);

        $this->at($this->moment(7, 16, 45), function () use ($user): void {
            $user->tokens()->delete();
            $user->forceFill(['deactivated_at' => now()])->save();

            $this->recordLog->handle(
                $user,
                UserLogEvent::Deactivated,
                $this->actor(),
                ['deactivated_at' => null],
                ['deactivated_at' => $user->deactivated_at],
            );
        });

        $this->at($this->moment(6, 8, 12), function () use ($user): void {
            $this->recordAttempt->handle($user, $user->email, LoginOutcome::AccountDeactivated, $this->theirDevice());
        });
    }

    /**
     * Started as a plain user and was promoted a fortnight ago -- switched off by
     * mistake along the way, and back on the same afternoon.
     */
    private function promoted(): void
    {
        $user = $this->createUser('Teodora', 'Marković', 'teodora.markovic@fifthguild.com', 34);

        $this->activated($user, 33);
        $this->signsInRegularly($user, from: 32, times: 17);

        $this->at($this->moment(26, 13, 5), function () use ($user): void {
            $user->tokens()->delete();
            $user->forceFill(['deactivated_at' => now()])->save();

            $this->recordLog->handle($user, UserLogEvent::Deactivated, $this->actor(),
                ['deactivated_at' => null], ['deactivated_at' => $user->deactivated_at]);
        });

        $this->at($this->moment(26, 15, 40), function () use ($user): void {
            $before = $user->deactivated_at;
            $user->forceFill(['deactivated_at' => null])->save();

            $this->recordLog->handle($user, UserLogEvent::Reactivated, $this->actor(),
                ['deactivated_at' => $before], ['deactivated_at' => null]);
        });

        $this->at($this->moment(15, 9, 50), function () use ($user): void {
            $user->forceFill(['role' => UserRole::Admin])->save();

            $this->recordLog->handle($user, UserLogEvent::RoleChanged, $this->actor(),
                ['role' => UserRole::User], ['role' => UserRole::Admin]);
        });
    }

    /**
     * Somebody working through invented addresses one evening. These rows belong
     * to no account -- which is the whole reason they are worth having.
     */
    private function strangersAtTheDoor(): void
    {
        $addresses = [
            'admin@fifthguild.com',
            'info@fifthguild.com',
            'test@fifthguild.com',
            'office@fifthguild.com',
            'support@fifthguild.com',
        ];

        foreach ($addresses as $index => $address) {
            $this->at($this->moment(3, 2, 14 + $index), function () use ($address): void {
                $this->recordAttempt->handle(null, $address, LoginOutcome::InvalidCredentials, $this->stranger());
            });
        }

        /** And two guesses at a real account, which failed on the password. */
        $marko = User::query()->where('email', 'marko.petrovic-ilic@fifthguild.com')->sole();

        foreach ([20, 21] as $minute) {
            $this->at($this->moment(3, 2, $minute), function () use ($marko): void {
                $this->recordAttempt->handle($marko, $marko->email, LoginOutcome::InvalidCredentials, $this->stranger());
            });
        }
    }

    /**
     * Create the account the way the application does -- an administrator adds
     * it, and the activation email goes out -- minus the actual email.
     */
    private function createUser(
        string $name,
        string $surname,
        string $email,
        int $daysAgo,
        UserRole $role = UserRole::User,
    ): User {
        $createdAt = $this->moment($daysAgo, 9, 30);

        return $this->at($createdAt, function () use ($name, $surname, $email, $role): User {
            $user = User::factory()->create([
                'name' => $name,
                'surname' => $surname,
                'email' => $email,
                'role' => $role,
                'password' => self::PASSWORD,
                'email_verified_at' => null,
            ]);

            $this->recordLog->handle($user, UserLogEvent::Created, $this->actor(), new: [
                'name' => $name,
                'surname' => $surname,
                'email' => $email,
                'role' => $role,
            ]);

            $this->recordLog->handle($user, UserLogEvent::ActivationLinkSent, $this->actor());

            return $user;
        });
    }

    /**
     * The user follows the link and sets their own password.
     */
    private function activated(User $user, int $daysAgo): void
    {
        $this->at($this->moment($daysAgo, 14, 12), function () use ($user): void {
            $user->forceFill(['email_verified_at' => now()])->save();

            $this->recordLog->handle(
                $user,
                UserLogEvent::Activated,
                $this->theirDevice()->actingAs($user->id),
                ['email_verified_at' => null],
                ['email_verified_at' => $user->email_verified_at],
            );

            $this->recordAttempt->handle($user, $user->email, LoginOutcome::Succeeded, $this->theirDevice());
        });
    }

    /**
     * Working days sprinkled with the odd mistyped password.
     */
    private function signsInRegularly(User $user, int $from, int $times, int $until = 0): void
    {
        /**
         * Spread across the whole span rather than stepping by whole days:
         * with an integer step, twenty sign-ins over six weeks all land in the
         * first three, and the list looks abandoned exactly where it should
         * look busiest -- at the top.
         */
        $span = $from - $until;

        for ($i = 0; $i < $times; $i++) {
            $daysAgo = $from - (int) round($i * $span / max(1, $times - 1));

            if ($daysAgo < $until) {
                break;
            }

            $this->at($this->moment($daysAgo, 8 + ($i % 9), ($i * 7) % 60), function () use ($user, $i): void {
                if ($i % 5 === 3) {
                    $this->recordAttempt->handle($user, $user->email, LoginOutcome::InvalidCredentials, $this->theirDevice());
                }

                $this->recordAttempt->handle($user, $user->email, LoginOutcome::Succeeded, $this->theirDevice());
            });
        }
    }

    /**
     * Freeze the clock, do the work, put it back.
     *
     * Everything downstream reads now() -- the log row's created_at, the
     * deactivated_at stamp, the login attempt's timestamp -- so this is all it
     * takes to place an event in the past without writing dates by hand.
     *
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    private function at(CarbonImmutable $when, Closure $work): mixed
    {
        Carbon::setTestNow($when);

        try {
            return $work();
        } finally {
            Carbon::setTestNow();
        }
    }

    private function moment(int $daysAgo, int $hour, int $minute): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays($daysAgo)->setTime($hour, $minute % 60);
    }

    /**
     * An administrator at the office, doing the change.
     */
    private function actor(): ActorData
    {
        return new ActorData(
            id: $this->admin->id,
            ipAddress: '10.20.0.14',
            userAgent: 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/128.0 Safari/537.36',
        );
    }

    /**
     * The user themselves, from wherever they happen to be.
     */
    private function theirDevice(): ActorData
    {
        return new ActorData(
            ipAddress: '77.243.18.'.random_int(2, 250),
            userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_2 like Mac OS X) AppleWebKit/605.1.15 Version/18.2 Mobile Safari/604.1',
        );
    }

    /**
     * Whoever was knocking on the door at two in the morning.
     */
    private function stranger(): ActorData
    {
        return new ActorData(
            ipAddress: '185.62.190.41',
            userAgent: 'python-requests/2.32.3',
        );
    }
}

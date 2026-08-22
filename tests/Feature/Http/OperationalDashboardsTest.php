<?php

namespace Tests\Feature\Http;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Who may open Horizon and Pulse once the application is not running locally.
 *
 * Both ship defaults that are effectively "local only": Horizon's generated
 * gate checks an empty list of email addresses, and Pulse allows the dashboard
 * only in the `local` environment. Deployed, that means the two are shut rather
 * than protected -- and the usual way that gets noticed is somebody opening the
 * gate up on the server, by hand, in a hurry.
 *
 * They read the same role as everything else, so a promotion applies from the
 * next request and a demotion closes the door without a redeploy.
 *
 * Pulse is asserted through the gate rather than through its route: the suite
 * runs with PULSE_ENABLED=false (phpunit.xml), so the route does not exist here.
 */
class OperationalDashboardsTest extends TestCase
{
    use RefreshDatabase;

    public static function dashboardProvider(): array
    {
        return [
            'horizon' => ['viewHorizon'],
            'pulse' => ['viewPulse'],
        ];
    }

    #[DataProvider('dashboardProvider')]
    public function test_an_administrator_may_open_it(string $ability): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->assertTrue(Gate::forUser($admin)->allows($ability));
    }

    #[DataProvider('dashboardProvider')]
    public function test_a_plain_user_may_not(string $ability): void
    {
        $user = User::factory()->create(['role' => UserRole::User]);

        $this->assertFalse(Gate::forUser($user)->allows($ability));
    }

    /**
     * Both gates take a nullable user precisely so an unauthenticated request
     * reaches them instead of erroring, which is the case that matters: these
     * dashboards sit outside the `auth:sanctum` group.
     */
    public function test_nobody_signed_in_may_not(): void
    {
        $this->assertFalse(Gate::allows('viewHorizon'));
        $this->assertFalse(Gate::allows('viewPulse'));
    }

    public function test_horizon_refuses_a_plain_user_at_the_route(): void
    {
        $this->actingAsUser(User::factory()->create(['role' => UserRole::User]));

        $this->get('/horizon')->assertForbidden();
    }

    public function test_horizon_refuses_an_anonymous_caller_at_the_route(): void
    {
        $this->get('/horizon')->assertForbidden();
    }
}

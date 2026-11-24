<?php

namespace Tests\Feature\Api\Audience;

use App\Http\Middleware\EnsureInternalUser;
use App\Http\Middleware\EnsureMobileUser;
use App\Models\MobileUser;
use App\Models\User;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Two populations sign in against one `auth:sanctum` guard: internal users and
 * the external app's users. Sanctum resolves a token to whichever model minted
 * it, so the only thing keeping a customer's token off the admin API is the
 * audience middleware each route group states.
 *
 * This walks **every** route rather than a sample: the failure it guards
 * against is a route added outside its group, and that route is by definition
 * one nobody thought to list.
 */
class AudienceSeparationTest extends TestCase
{
    public function test_every_authenticated_route_states_exactly_one_audience(): void
    {
        $routes = $this->authenticatedRoutes();

        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            $audiences = array_intersect([EnsureInternalUser::class, EnsureMobileUser::class], $middleware);

            $this->assertCount(1, $audiences, "{$this->describe($route)} must state exactly one audience.");
        }
    }

    public function test_a_mobile_users_token_is_refused_on_every_internal_route(): void
    {
        Sanctum::actingAs(MobileUser::factory()->create());

        foreach ($this->routesFor(EnsureInternalUser::class) as $route) {
            $this->json($this->methodOf($route), $this->urlOf($route))
                ->assertForbidden()
                ->assertJsonPath('message', __('mobile.access.internal_only', [], 'sr'));
        }
    }

    public function test_an_internal_users_token_is_refused_on_every_mobile_route(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $this->withHeader('Accept-Language', 'sr');

        foreach ($this->routesFor(EnsureMobileUser::class) as $route) {
            $this->json($this->methodOf($route), $this->urlOf($route))
                ->assertForbidden()
                ->assertJsonPath('message', __('mobile.access.mobile_only', [], 'sr'));
        }
    }

    public function test_both_route_sets_are_actually_exercised(): void
    {
        $this->assertGreaterThan(10, count($this->routesFor(EnsureInternalUser::class)));
        $this->assertGreaterThan(5, count($this->routesFor(EnsureMobileUser::class)));
    }

    /**
     * @return list<Route>
     */
    private function authenticatedRoutes(): array
    {
        return array_values(array_filter(
            Router::getRoutes()->getRoutes(),
            fn (Route $route): bool => in_array('auth:sanctum', $route->gatherMiddleware(), true),
        ));
    }

    /**
     * @return list<Route>
     */
    private function routesFor(string $audience): array
    {
        return array_values(array_filter(
            $this->authenticatedRoutes(),
            fn (Route $route): bool => in_array($audience, $route->gatherMiddleware(), true),
        ));
    }

    private function methodOf(Route $route): string
    {
        return collect($route->methods())->reject(fn (string $method): bool => $method === 'HEAD')->first();
    }

    /** Every parameter in these routes is a numeric id; one that does not exist is fine -- the audience is checked first. */
    private function urlOf(Route $route): string
    {
        return '/'.preg_replace('/\{[^}]+\}/', '999999', $route->uri());
    }

    private function describe(Route $route): string
    {
        return $this->methodOf($route).' '.$route->uri();
    }
}

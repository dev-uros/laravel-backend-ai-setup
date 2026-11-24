<?php

namespace Tests;

use App\Enums\UserRole;
use App\Models\MobileUser;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Sanctum\Sanctum;

abstract class TestCase extends BaseTestCase
{
    /**
     * Authenticate every request this test makes.
     *
     * The caller is deliberately not persisted. None of the endpoints read
     * anything off it, and a real row would turn up in the very lists, counts
     * and orderings the users tests assert on -- so saving it would make those
     * tests describe a dataset the test did not set up. Pass a user in when the
     * test needs the caller to exist in the database.
     */
    protected function actingAsUser(?User $user = null): static
    {
        Sanctum::actingAs($user ?? User::factory()->make());

        return $this;
    }

    /**
     * Authenticate as somebody who may do everything the API offers.
     *
     * Tests that exercise a write need this: UserPolicy lets a plain user read
     * and nothing else, so actingAsUser() would answer 403 there.
     */
    protected function actingAsAdmin(?User $user = null): static
    {
        return $this->actingAsUser($user ?? User::factory()->make(['role' => UserRole::Admin]));
    }

    /**
     * Symfony's test client sends `Accept-Language: en-us` on every request,
     * which `SetLocale` would honour. Mobile tests state the language they
     * expect instead of inheriting that.
     */
    protected function inSerbian(): static
    {
        return $this->withHeader('Accept-Language', 'sr');
    }

    /**
     * Authenticate as a user of the external mobile app. Persisted, because
     * every mobile endpoint reads the caller's own row.
     */
    protected function actingAsMobileUser(?MobileUser $user = null): MobileUser
    {
        $user ??= MobileUser::factory()->create();

        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * A real bearer token for a mobile user -- for the endpoints that read
     * `currentAccessToken()->id` (device, logout), which Sanctum::actingAs's
     * TransientToken does not have.
     *
     * @return array{0: MobileUser, 1: string}
     */
    protected function mobileUserWithToken(?MobileUser $user = null): array
    {
        $user ??= MobileUser::factory()->create();

        return [$user, $user->createToken(MobileUser::TOKEN_NAME)->plainTextToken];
    }
}

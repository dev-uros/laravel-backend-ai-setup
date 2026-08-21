<?php

namespace Tests;

use App\Enums\UserRole;
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
}

<?php

namespace Tests\Feature\Api\MobileUser;

use App\Models\MobileUser;
use App\Models\MobileUserDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin panel's view of the external app's accounts: list, search,
 * detail, devices, blocking.
 */
class AdminMobileUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_list_is_paginated_newest_first_with_device_counts(): void
    {
        $this->actingAsUser();
        $old = MobileUser::factory()->create();
        $new = MobileUser::factory()->create();
        MobileUserDevice::factory()->count(2)->create(['mobile_user_id' => $new->id]);

        $this->getJson(route('mobile-users.index'))
            ->assertOk()
            ->assertJsonPath('message', 'Korisnici aplikacije.')
            ->assertJsonPath('data.0.id', $new->id)
            ->assertJsonPath('data.0.devices_count', 2)
            ->assertJsonPath('data.1.id', $old->id)
            ->assertJsonPath('meta.per_page', 25)
            ->assertJsonStructure(['links', 'meta']);
    }

    public function test_search_matches_username_names_and_address_case_insensitively(): void
    {
        $this->actingAsUser();
        $ana = MobileUser::factory()->create(['username' => 'ana', 'first_name' => 'Ana', 'last_name' => 'Petrović', 'email' => 'ana@example.com']);
        MobileUser::factory()->create(['username' => 'pera', 'first_name' => 'Petar', 'last_name' => 'Ilić', 'email' => 'pera@example.com']);

        foreach (['ANA', 'petrović', 'Ana Petr', 'ana@exa'] as $term) {
            $this->getJson(route('mobile-users.index', ['search' => $term]))
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $ana->id);
        }

        $this->getJson(route('mobile-users.index', ['search' => '%']))->assertJsonCount(0, 'data');
    }

    public function test_the_list_filters_by_state_and_leaves_deleted_accounts_out(): void
    {
        $this->actingAsUser();
        $blocked = MobileUser::factory()->blocked()->create();
        MobileUser::factory()->create();
        MobileUser::factory()->create()->delete();

        $this->getJson(route('mobile-users.index'))->assertJsonCount(2, 'data');
        $this->getJson(route('mobile-users.index', ['blocked' => 1]))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $blocked->id);
        $this->getJson(route('mobile-users.index', ['blocked' => 0]))->assertJsonCount(1, 'data');
    }

    public function test_the_detail_and_devices(): void
    {
        $this->actingAsUser();
        $user = MobileUser::factory()->withApple()->create();
        MobileUserDevice::factory()->withPushToken()->create(['mobile_user_id' => $user->id]);

        $this->getJson(route('mobile-users.show', $user->id))
            ->assertOk()
            ->assertJsonPath('data.has_apple', true)
            ->assertJsonMissingPath('data.devices_count')
            ->assertJsonMissingPath('data.apple_refresh_token');

        $this->getJson(route('mobile-users.devices.index', $user->id))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.push_notifications_enabled', true);

        $user->delete();
        $this->getJson(route('mobile-users.show', $user->id))
            ->assertNotFound()
            ->assertJsonPath('message', 'Korisnik aplikacije nije pronađen.');
    }

    public function test_an_administrator_blocks_and_unblocks(): void
    {
        $this->actingAsAdmin(User::factory()->admin()->create());
        [$user, $token] = $this->mobileUserWithToken();

        $this->postJson(route('mobile-users.block', $user->id))
            ->assertOk()
            ->assertJsonPath('message', 'Nalog je blokiran.')
            ->assertJsonPath('data.blocked_at', fn (?string $at): bool => $at !== null);

        $this->assertSame(0, $user->tokens()->count());

        $this->postJson(route('mobile-users.unblock', $user->id))
            ->assertOk()
            ->assertJsonPath('data.blocked_at', null);
    }

    public function test_a_plain_user_may_read_but_not_block(): void
    {
        $this->actingAsUser();
        $user = MobileUser::factory()->create();

        $this->getJson(route('mobile-users.show', $user->id))->assertOk();
        $this->postJson(route('mobile-users.block', $user->id))->assertForbidden();
        $this->postJson(route('mobile-users.unblock', $user->id))->assertForbidden();
        $this->assertNull($user->fresh()->blocked_at);
    }
}

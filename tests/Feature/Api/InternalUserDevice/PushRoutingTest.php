<?php

namespace Tests\Feature\Api\InternalUserDevice;

use App\Models\InternalUserDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationChannels\Expo\ExpoPushToken;
use Tests\TestCase;

/**
 * Where a push to a user goes. The rule is in the schema, not in each flow
 * that ends a session: a device is reachable while it holds a push token AND
 * a live session, and deleting the token nulls the session column.
 */
class PushRoutingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    private function routes(User $user): array
    {
        return array_map(
            fn (ExpoPushToken $token): string => $token->asString(),
            $user->refresh()->routeNotificationForExpo(),
        );
    }

    public function test_it_reaches_every_signed_in_phone_that_allows_notifications(): void
    {
        $user = User::factory()->create();
        $a = InternalUserDevice::factory()->for($user)->signedIn()->withPushToken()->create();
        $b = InternalUserDevice::factory()->for($user)->signedIn()->withPushToken()->create();
        InternalUserDevice::factory()->for($user)->signedIn()->create();
        InternalUserDevice::factory()->for($user)->withPushToken()->create();
        InternalUserDevice::factory()->signedIn()->withPushToken()->create();

        $this->assertEqualsCanonicalizing(
            [$a->expo_push_notification_token, $b->expo_push_notification_token],
            $this->routes($user),
        );
    }

    public function test_signing_out_stops_pushes_to_that_phone_only(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile');
        $here = InternalUserDevice::factory()->for($user)->withPushToken()->create(['personal_access_token_id' => $token->accessToken->id]);
        $other = InternalUserDevice::factory()->for($user)->signedIn()->withPushToken()->create();

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson(route('auth.logout'))
            ->assertOk();

        $this->assertSame([$other->expo_push_notification_token], $this->routes($user));
        $this->assertModelExists($here);
        $this->assertNull($here->refresh()->personal_access_token_id);
    }

    public function test_a_switched_off_account_is_reached_nowhere(): void
    {
        $user = User::factory()->create();
        InternalUserDevice::factory()->for($user)->signedIn()->withPushToken()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAsAdmin($admin)->postJson(route('users.deactivate', $user->id))->assertOk();

        $this->assertSame([], $this->routes($user));
    }

    public function test_signing_in_again_on_the_phone_resumes_its_pushes(): void
    {
        $user = User::factory()->create(['password' => 'tajna-lozinka']);
        $device = InternalUserDevice::factory()->for($user)->withPushToken()->create(['platform' => 'ios']);

        $this->postJson(route('auth.login'), [
            'email' => $user->email,
            'password' => 'tajna-lozinka',
            'device' => ['device_id' => $device->device_id, 'platform' => 'ios'],
        ])->assertOk();

        $this->assertSame([$device->expo_push_notification_token], $this->routes($user));
    }
}

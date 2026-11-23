<?php

namespace Tests\Feature\Api\InternalUserDevice;

use App\Models\InternalUserDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\NewAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * `GET` and `PUT /auth/device`: the phone the calling session is on. Real
 * tokens throughout -- the device is found by the session, and the TransientToken
 * of Sanctum::actingAs() has no row to find it by.
 */
class CurrentDeviceTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'ExponentPushToken[abcdefghijklmnopqrstuv]';

    /**
     * @return array<string, string>
     */
    private function body(array $overrides = []): array
    {
        return [
            'device_id' => 'phone-1',
            'platform' => 'ios',
            'name' => 'iPhone 17 Pro',
            'operating_system' => 'iOS',
            'manufacturer' => 'Apple',
            'os_version' => '27.2',
            ...$overrides,
        ];
    }

    private function as(NewAccessToken $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken);
    }

    public function test_it_shows_the_phone_of_this_session_only(): void
    {
        $user = User::factory()->create();
        $here = $user->createToken('mobile');
        $elsewhere = $user->createToken('mobile');
        InternalUserDevice::factory()->for($user)->create(['personal_access_token_id' => $elsewhere->accessToken->id, 'name' => 'Drugi telefon']);
        InternalUserDevice::factory()->for($user)->withPushToken()->create(['personal_access_token_id' => $here->accessToken->id, 'name' => 'Ovaj telefon']);

        $this->as($here)->getJson(route('auth.device.show'))
            ->assertOk()
            ->assertJsonPath('message', 'Podaci o uređaju.')
            ->assertJsonPath('data.name', 'Ovaj telefon')
            ->assertJsonPath('data.push_notifications_enabled', true)
            ->assertJsonMissingPath('data.expo_push_notification_token');
    }

    public function test_a_session_without_a_phone_answers_404(): void
    {
        $token = User::factory()->create()->createToken('spa');

        $this->as($token)->getJson(route('auth.device.show'))
            ->assertNotFound()
            ->assertJsonPath('message', 'Ovaj uređaj nije registrovan.');
    }

    public function test_put_registers_the_phone_when_the_session_has_none(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile');

        $this->as($token)->putJson(route('auth.device.update'), $this->body())
            ->assertOk()
            ->assertJsonPath('message', 'Uređaj je sačuvan.')
            ->assertJsonPath('data.device_id', 'phone-1')
            ->assertJsonPath('data.platform', 'ios')
            ->assertJsonPath('data.push_notifications_enabled', false);

        $device = InternalUserDevice::query()->sole();
        $this->assertSame($user->id, $device->user_id);
        $this->assertSame($token->accessToken->id, $device->personal_access_token_id);
    }

    public function test_put_without_the_token_key_keeps_the_stored_one(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile');
        $device = InternalUserDevice::factory()->for($user)->withPushToken(self::TOKEN)
            ->create(['device_id' => 'phone-1', 'personal_access_token_id' => $token->accessToken->id, 'last_seen_at' => now()->subWeek()]);

        $this->as($token)->putJson(route('auth.device.update'), $this->body(['os_version' => '27.3']))
            ->assertOk()
            ->assertJsonPath('data.push_notifications_enabled', true);

        $device->refresh();
        $this->assertSame(self::TOKEN, $device->expo_push_notification_token);
        $this->assertSame('27.3', $device->os_version);
        $this->assertTrue($device->last_seen_at->isToday(), 'a launch counts as being seen');
    }

    public function test_put_stores_a_push_token(): void
    {
        $token = User::factory()->create()->createToken('mobile');

        $this->as($token)->putJson(route('auth.device.update'), $this->body(['expo_push_notification_token' => self::TOKEN]))
            ->assertOk()
            ->assertJsonPath('data.push_notifications_enabled', true);

        $this->assertSame(self::TOKEN, InternalUserDevice::query()->sole()->expo_push_notification_token);
    }

    public function test_put_with_null_switches_notifications_off(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile');
        InternalUserDevice::factory()->for($user)->withPushToken(self::TOKEN)
            ->create(['device_id' => 'phone-1', 'personal_access_token_id' => $token->accessToken->id]);

        $this->as($token)->putJson(route('auth.device.update'), $this->body(['expo_push_notification_token' => null]))
            ->assertOk()
            ->assertJsonPath('data.push_notifications_enabled', false);

        $this->assertNull(InternalUserDevice::query()->sole()->expo_push_notification_token);
    }

    /**
     * One phone, handed from one user to another: the token moves with the
     * phone, and the first user's row must let go of it.
     */
    public function test_a_push_token_moves_to_whoever_is_signed_in_on_the_phone_now(): void
    {
        $previous = InternalUserDevice::factory()->withPushToken(self::TOKEN)->create(['device_id' => 'phone-1']);
        $token = User::factory()->create()->createToken('mobile');

        $this->as($token)->putJson(route('auth.device.update'), $this->body(['expo_push_notification_token' => self::TOKEN]))
            ->assertOk();

        $this->assertNull($previous->refresh()->expo_push_notification_token);
        $this->assertSame(1, InternalUserDevice::query()->where('expo_push_notification_token', self::TOKEN)->count());
    }

    /**
     * The same session reporting a different phone id (iOS hands out a new
     * vendor id on reinstall, but the token would be gone then too -- this is
     * the defensive case): the session moves to the new row.
     */
    public function test_a_session_is_bound_to_one_phone_at_a_time(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile');
        $old = InternalUserDevice::factory()->for($user)->create(['device_id' => 'old', 'personal_access_token_id' => $token->accessToken->id]);

        $this->as($token)->putJson(route('auth.device.update'), $this->body(['device_id' => 'new']))->assertOk();

        $this->assertNull($old->refresh()->personal_access_token_id);
        $this->assertSame('new', InternalUserDevice::query()->where('personal_access_token_id', $token->accessToken->id)->sole()->device_id);
    }

    #[DataProvider('invalidBodies')]
    public function test_a_malformed_body_is_refused(array $body, string $field): void
    {
        $token = User::factory()->create()->createToken('mobile');

        $this->as($token)->putJson(route('auth.device.update'), $body)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidBodies(): array
    {
        $valid = ['device_id' => 'phone-1', 'platform' => 'android'];

        return [
            'bez identifikatora' => [['platform' => 'android'], 'device_id'],
            'bez platforme' => [['device_id' => 'phone-1'], 'platform'],
            'token koji nije Expo' => [[...$valid, 'expo_push_notification_token' => 'fcm:abcdefghijklmnopqrstuvwxyz'], 'expo_push_notification_token'],
        ];
    }

    public function test_every_device_endpoint_needs_a_token(): void
    {
        $this->getJson(route('auth.device.show'))->assertUnauthorized();
        $this->putJson(route('auth.device.update'), $this->body())->assertUnauthorized();
        $this->postJson(route('auth.device.test-push'))->assertUnauthorized();
    }
}

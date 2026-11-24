<?php

namespace Tests\Feature\Api\Mobile;

use App\Models\MobileUser;
use App\Models\MobileUserDevice;
use App\Notifications\MobileTestPushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Expo\Events\ExpoNotificationFailed;
use NotificationChannels\Expo\ExpoError;
use NotificationChannels\Expo\ExpoErrorType;
use NotificationChannels\Expo\ExpoPushToken;
use Tests\TestCase;

/**
 * The phone a session is on, its push token, and signing out.
 */
class MobileDeviceTest extends TestCase
{
    use RefreshDatabase;

    private const PUSH_TOKEN = 'ExponentPushToken[aaaaaaaaaaaaaaaaaaaaaa]';

    protected function setUp(): void
    {
        parent::setUp();

        $this->inSerbian();
    }

    public function test_the_phone_is_registered_with_its_push_token(): void
    {
        [$user, $token] = $this->mobileUserWithToken();

        $this->putJson(route('mobile.device.update'), [
            'device_id' => 'phone-1',
            'platform' => 'android',
            'expo_push_notification_token' => self::PUSH_TOKEN,
        ], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('data.push_notifications_enabled', true)
            ->assertJsonPath('data.signed_in', true);

        $this->assertSame([self::PUSH_TOKEN], array_map(fn (ExpoPushToken $token): string => $token->asString(), $user->routeNotificationForExpo()));
    }

    public function test_a_phone_handed_to_somebody_else_stops_reaching_the_first_person(): void
    {
        $first = MobileUser::factory()->create();
        MobileUserDevice::factory()->signedIn()->withPushToken(self::PUSH_TOKEN)->create(['mobile_user_id' => $first->id]);
        [, $token] = $this->mobileUserWithToken();

        $this->putJson(route('mobile.device.update'), ['device_id' => 'phone-1', 'platform' => 'ios', 'expo_push_notification_token' => self::PUSH_TOKEN], ['Authorization' => "Bearer {$token}"])
            ->assertOk();

        $this->assertSame([], $first->routeNotificationForExpo());
    }

    public function test_signing_out_revokes_the_token_and_clears_the_push_token(): void
    {
        [$user, $token] = $this->mobileUserWithToken();
        [, $otherPhone] = $this->mobileUserWithToken($user);
        $this->putJson(route('mobile.device.update'), ['device_id' => 'phone-1', 'platform' => 'ios', 'expo_push_notification_token' => self::PUSH_TOKEN], ['Authorization' => "Bearer {$token}"]);

        $this->postJson(route('mobile.auth.logout'), [], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonPath('message', 'Uspešno ste odjavljeni.');

        $device = MobileUserDevice::query()->sole();
        $this->assertNull($device->expo_push_notification_token);
        $this->assertNull($device->personal_access_token_id);

        $this->app['auth']->forgetGuards();
        $this->getJson(route('mobile.me.show'), ['Authorization' => "Bearer {$token}"])->assertUnauthorized();
        $this->getJson(route('mobile.me.show'), ['Authorization' => "Bearer {$otherPhone}"])->assertOk();
    }

    public function test_a_test_push_goes_to_this_phone_in_the_apps_language(): void
    {
        Notification::fake();
        [, $token] = $this->mobileUserWithToken();
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->postJson(route('mobile.device.test-push'), [], $headers)->assertNotFound();

        $this->putJson(route('mobile.device.update'), ['device_id' => 'phone-1', 'platform' => 'ios'], $headers);
        $this->postJson(route('mobile.device.test-push'), [], $headers)->assertConflict();

        $this->putJson(route('mobile.device.update'), ['device_id' => 'phone-1', 'platform' => 'ios', 'expo_push_notification_token' => self::PUSH_TOKEN], $headers);
        $this->postJson(route('mobile.device.test-push'), [], [...$headers, 'Accept-Language' => 'en'])->assertOk();

        Notification::assertSentOnDemand(MobileTestPushNotification::class, fn (MobileTestPushNotification $notification): bool => $notification->locale === 'en');
    }

    public function test_expo_reporting_the_phone_gone_clears_its_token(): void
    {
        $device = MobileUserDevice::factory()->signedIn()->withPushToken(self::PUSH_TOKEN)->create();

        event(new ExpoNotificationFailed(
            $device->mobileUser,
            new MobileTestPushNotification,
            ExpoError::make(ExpoErrorType::DeviceNotRegistered, ExpoPushToken::make(self::PUSH_TOKEN), 'gone'),
        ));

        $this->assertNull($device->fresh()->expo_push_notification_token);
    }

    public function test_notifications_to_an_account_follow_its_language(): void
    {
        Notification::fake();
        $user = MobileUser::factory()->create(['locale' => 'en']);

        $user->notify(new MobileTestPushNotification);

        Notification::assertSentTo($user, MobileTestPushNotification::class, fn ($notification, $channels, $notifiable, string $locale): bool => $locale === 'en');
    }
}

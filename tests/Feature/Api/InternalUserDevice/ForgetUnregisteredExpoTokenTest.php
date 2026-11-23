<?php

namespace Tests\Feature\Api\InternalUserDevice;

use App\Models\InternalUserDevice;
use App\Notifications\TestPushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use NotificationChannels\Expo\Events\ExpoNotificationFailed;
use NotificationChannels\Expo\ExpoError;
use NotificationChannels\Expo\ExpoErrorType;
use NotificationChannels\Expo\ExpoPushToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Fired through the dispatcher rather than by calling the listener, so the
 * test also fails if event discovery stops picking it up.
 */
class ForgetUnregisteredExpoTokenTest extends TestCase
{
    use RefreshDatabase;

    private function fire(ExpoErrorType $type, string $token): void
    {
        event(new ExpoNotificationFailed(
            new AnonymousNotifiable,
            new TestPushNotification,
            ExpoError::make($type, ExpoPushToken::make($token), 'poruka'),
        ));
    }

    public function test_it_forgets_the_token_of_a_phone_that_is_gone(): void
    {
        $device = InternalUserDevice::factory()->withPushToken()->create();

        $this->fire(ExpoErrorType::DeviceNotRegistered, $device->expo_push_notification_token);

        $this->assertNull($device->refresh()->expo_push_notification_token);
        $this->assertModelExists($device);
    }

    #[DataProvider('ourOwnErrors')]
    public function test_it_keeps_the_token_when_the_problem_is_ours(ExpoErrorType $type): void
    {
        $device = InternalUserDevice::factory()->withPushToken()->create();
        $token = $device->expo_push_notification_token;

        $this->fire($type, $token);

        $this->assertSame($token, $device->refresh()->expo_push_notification_token);
    }

    /**
     * @return array<string, array{ExpoErrorType}>
     */
    public static function ourOwnErrors(): array
    {
        return [
            'poruka prevelika' => [ExpoErrorType::MessageTooBig],
            'prebrzo slanje' => [ExpoErrorType::MessageRateExceeded],
            'pogrešan pošiljalac' => [ExpoErrorType::MismatchSenderId],
            'loši ključevi' => [ExpoErrorType::InvalidCredentials],
        ];
    }
}

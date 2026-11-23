<?php

namespace Tests\Feature\Api\InternalUserDevice;

use App\Models\InternalUserDevice;
use App\Models\User;
use App\Notifications\TestPushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\NewAccessToken;
use NotificationChannels\Expo\ExpoChannel;
use NotificationChannels\Expo\ExpoPushToken;
use Tests\TestCase;

class SendTestPushTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function as(NewAccessToken $token): static
    {
        return $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken);
    }

    public function test_it_sends_to_this_phone_and_not_the_accounts_others(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile');
        $here = InternalUserDevice::factory()->for($user)->withPushToken()->create(['personal_access_token_id' => $token->accessToken->id]);
        InternalUserDevice::factory()->for($user)->signedIn()->withPushToken()->create();

        $this->as($token)->postJson(route('auth.device.test-push'))
            ->assertOk()
            ->assertExactJson(['message' => 'Probno obaveštenje je poslato.', 'data' => null]);

        Notification::assertSentOnDemandTimes(TestPushNotification::class, 1);
        Notification::assertSentOnDemand(
            TestPushNotification::class,
            function (TestPushNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($here): bool {
                $route = $notifiable->routeNotificationFor('expo');

                return $route instanceof ExpoPushToken
                    && $route->equals($here->expo_push_notification_token)
                    && $channels === [ExpoChannel::class];
            },
        );
    }

    public function test_the_message_is_in_serbian(): void
    {
        $message = (new TestPushNotification)->toExpo(new AnonymousNotifiable)->toArray();

        $this->assertSame('Probno obaveštenje', $message['title']);
        $this->assertSame('Obaveštenja rade na ovom uređaju.', $message['body']);
    }

    public function test_a_phone_without_notifications_answers_409(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile');
        InternalUserDevice::factory()->for($user)->create(['personal_access_token_id' => $token->accessToken->id]);

        $this->as($token)->postJson(route('auth.device.test-push'))
            ->assertConflict()
            ->assertJsonPath('message', 'Obaveštenja nisu uključena na ovom uređaju.');

        Notification::assertNothingSent();
    }

    public function test_a_session_without_a_phone_answers_404(): void
    {
        $token = User::factory()->create()->createToken('spa');

        $this->as($token)->postJson(route('auth.device.test-push'))->assertNotFound();

        Notification::assertNothingSent();
    }
}

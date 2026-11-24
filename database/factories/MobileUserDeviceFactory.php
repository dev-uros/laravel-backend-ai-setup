<?php

namespace Database\Factories;

use App\Enums\DevicePlatform;
use App\Models\MobileUser;
use App\Models\MobileUserDevice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MobileUserDevice>
 */
class MobileUserDeviceFactory extends Factory
{
    /**
     * A phone with no session and no push token.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mobile_user_id' => MobileUser::factory(),
            'personal_access_token_id' => null,
            'device_id' => fake()->unique()->uuid(),
            'name' => 'iPhone 17 Pro',
            'platform' => DevicePlatform::Ios,
            'operating_system' => 'iOS',
            'manufacturer' => 'Apple',
            'os_version' => '27.2',
            'expo_push_notification_token' => null,
            'last_seen_at' => now(),
        ];
    }

    public function withPushToken(?string $token = null): static
    {
        return $this->state(fn (array $attributes) => [
            'expo_push_notification_token' => $token ?? 'ExponentPushToken['.fake()->unique()->regexify('[A-Za-z0-9]{22}').']',
        ]);
    }

    /**
     * Somebody is signed in on this phone right now, with a real token.
     */
    public function signedIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'personal_access_token_id' => fn (array $attributes) => MobileUser::query()
                ->findOrFail($attributes['mobile_user_id'])
                ->createToken(MobileUser::TOKEN_NAME)
                ->accessToken
                ->id,
        ]);
    }
}

<?php

namespace Database\Factories;

use App\Enums\DevicePlatform;
use App\Models\InternalUserDevice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternalUserDevice>
 */
class InternalUserDeviceFactory extends Factory
{
    /**
     * A phone with no session and no push token -- somebody signed in on it
     * once and has since signed out.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'personal_access_token_id' => null,
            'device_id' => fake()->unique()->uuid(),
            'name' => 'Pixel 8',
            'platform' => DevicePlatform::Android,
            'operating_system' => 'Android',
            'manufacturer' => 'Google',
            'os_version' => '16',
            'expo_push_notification_token' => null,
            'last_seen_at' => now(),
        ];
    }

    /**
     * Notifications allowed on this phone.
     */
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
            'personal_access_token_id' => fn (array $attributes) => User::query()
                ->findOrFail($attributes['user_id'])
                ->createToken('mobile')
                ->accessToken
                ->id,
        ]);
    }
}

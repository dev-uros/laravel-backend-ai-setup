<?php

namespace Database\Factories;

use App\Enums\UserLogEvent;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserLog>
 */
class UserLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'actor_id' => User::factory()->admin(),
            'event' => UserLogEvent::Updated,
            'old_values' => ['name' => fake()->firstName()],
            'new_values' => ['name' => fake()->firstName()],
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'Symfony',
            'created_at' => now(),
        ];
    }
}

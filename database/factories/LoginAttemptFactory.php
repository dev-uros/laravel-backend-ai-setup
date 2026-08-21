<?php

namespace Database\Factories;

use App\Enums\LoginOutcome;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoginAttempt>
 */
class LoginAttemptFactory extends Factory
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
            'email' => fake()->unique()->safeEmail(),
            'outcome' => LoginOutcome::Succeeded,
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'Symfony',
            'created_at' => now(),
        ];
    }

    /**
     * An attempt that was turned away.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'outcome' => LoginOutcome::InvalidCredentials,
        ]);
    }

    /**
     * An attempt on an address nobody uses.
     */
    public function unknownAddress(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'outcome' => LoginOutcome::InvalidCredentials,
        ]);
    }
}

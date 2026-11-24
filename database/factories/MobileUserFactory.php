<?php

namespace Database\Factories;

use App\Enums\AppLocale;
use App\Models\MobileUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MobileUser>
 */
class MobileUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'username' => mb_strtolower(fake()->unique()->regexify('[a-z]{5,10}[0-9]{2}')),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'display_personal_name' => false,
            'email' => mb_strtolower(fake()->unique()->safeEmail()),
            'locale' => AppLocale::Sr,
            'is_demo' => false,
        ];
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => ['blocked_at' => now()]);
    }

    public function demo(): static
    {
        return $this->state(fn (array $attributes) => ['is_demo' => true]);
    }

    public function withGoogle(?string $sub = null): static
    {
        return $this->state(fn (array $attributes) => ['google_id' => $sub ?? fake()->unique()->numerify('1#################')]);
    }

    public function withApple(?string $sub = null): static
    {
        return $this->state(fn (array $attributes) => ['apple_id' => $sub ?? fake()->unique()->numerify('000###.################.####')]);
    }
}

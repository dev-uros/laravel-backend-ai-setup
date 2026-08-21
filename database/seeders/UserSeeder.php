<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * The password given to every seeded user.
     *
     * Local development only. The `hashed` cast on the model hashes it on save.
     */
    private const PASSWORD = 'password';

    /**
     * Seed the application's users.
     *
     * Safe to re-run: existing accounts are matched on their email address and
     * left untouched.
     */
    public function run(): void
    {
        /** @var array<int, array{name: string, surname: string, email: string, role: UserRole}> $users */
        $users = [
            [
                'name' => 'Uroš',
                'surname' => 'Minić',
                'email' => 'uros.minic@fifthguild.com',
                'role' => UserRole::Admin,
            ],
        ];

        foreach ($users as $user) {
            if (User::query()->where('email', $user['email'])->exists()) {
                continue;
            }

            User::factory()->create([
                ...$user,
                'password' => self::PASSWORD,
            ]);
        }
    }
}

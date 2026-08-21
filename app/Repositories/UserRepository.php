<?php

namespace App\Repositories;

use App\Data\User\StoreUserData;
use App\Data\User\UpdateUserData;
use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class UserRepository implements UserRepositoryInterface
{
    /**
     * Every user, ordered by name and then surname.
     *
     * @return Collection<int, User>
     */
    public function allSortedByName(): Collection
    {
        return User::query()
            ->orderBy('name')
            ->orderBy('surname')
            ->get();
    }

    /**
     * A single user, or null when no user has that id.
     */
    public function findById(int $id): ?User
    {
        return User::query()->find($id);
    }

    /**
     * A single user by email address, or null when nobody uses it.
     */
    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    /**
     * Persist new details for a user and return it freshly loaded.
     */
    public function update(User $user, UpdateUserData $data): User
    {
        $user->update([
            'name' => $data->name,
            'surname' => $data->surname,
            'email' => $data->email,
        ]);

        return $user->refresh();
    }

    /**
     * Store a new user. The password arrives already generated; the model's
     * `hashed` cast hashes it on save.
     */
    public function create(StoreUserData $data, string $password): User
    {
        return User::query()->create([
            'name' => $data->name,
            'surname' => $data->surname,
            'email' => $data->email,
            'role' => $data->role,
            'password' => $password,
        ]);
    }

    /**
     * Set the user's chosen password and mark their email as verified.
     *
     * forceFill because `email_verified_at` is intentionally not fillable; the
     * model's `hashed` cast still hashes the password on save.
     */
    public function activate(User $user, string $password): User
    {
        $user->forceFill([
            'password' => $password,
            'email_verified_at' => now(),
        ])->save();

        return $user->refresh();
    }

    /**
     * Replace the user's password, leaving email verification untouched.
     */
    public function updatePassword(User $user, string $password): User
    {
        $user->forceFill(['password' => $password])->save();

        return $user->refresh();
    }

    /**
     * Give the user a different role.
     */
    public function updateRole(User $user, UserRole $role): User
    {
        $user->forceFill(['role' => $role])->save();

        return $user->refresh();
    }

    /**
     * Switch the account off, stamping when it happened. Idempotent: an account
     * that is already off keeps the timestamp it already has.
     */
    public function deactivate(User $user): User
    {
        if ($user->isActive()) {
            $user->forceFill(['deactivated_at' => now()])->save();
        }

        return $user->refresh();
    }

    /**
     * Switch the account back on.
     */
    public function reactivate(User $user): User
    {
        $user->forceFill(['deactivated_at' => null])->save();

        return $user->refresh();
    }
}

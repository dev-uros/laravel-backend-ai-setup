<?php

namespace App\Repositories\Contracts;

use App\Data\User\StoreUserData;
use App\Data\User\UpdateUserData;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface UserRepositoryInterface
{
    /**
     * Every user, ordered by name and then surname.
     *
     * @return Collection<int, User>
     */
    public function allSortedByName(): Collection;

    /**
     * A single user, or null when no user has that id.
     */
    public function findById(int $id): ?User;

    /**
     * A single user by email address, or null when nobody uses it.
     */
    public function findByEmail(string $email): ?User;

    /**
     * Persist new details for a user and return it freshly loaded.
     */
    public function update(User $user, UpdateUserData $data): User;

    /**
     * Store a new user. The password arrives already generated; the model's
     * `hashed` cast hashes it on save.
     */
    public function create(StoreUserData $data, string $password): User;

    /**
     * Set the user's chosen password and mark their email as verified.
     */
    public function activate(User $user, string $password): User;

    /**
     * Replace the user's password, leaving email verification untouched.
     */
    public function updatePassword(User $user, string $password): User;

    /**
     * Give the user a different role.
     */
    public function updateRole(User $user, UserRole $role): User;

    /**
     * Switch the account off, stamping when it happened. Idempotent: an account
     * that is already off keeps the timestamp it already has.
     */
    public function deactivate(User $user): User;

    /**
     * Switch the account back on.
     */
    public function reactivate(User $user): User;
}

<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * What internal users may do with the external app's accounts, from the admin
 * panel. Applied by `can:` on the routes; every method takes only the caller.
 *
 * The same split as `UserPolicy`: anyone signed in may read, only an
 * administrator may write. The only write is blocking -- everything else about
 * such an account is its owner's to change, from the app.
 */
class MobileUserPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->role === UserRole::Admin ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user): bool
    {
        return true;
    }

    public function block(User $user): bool
    {
        return false;
    }
}

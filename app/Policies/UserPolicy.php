<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

/**
 * Who may do what with user accounts.
 *
 * Applied by the `can:` middleware in routes/api.php rather than inside the
 * controllers, so the answer to "who can reach this endpoint" is readable from
 * the route file.
 *
 * Every method here takes only the caller. That is deliberate: the routes carry
 * a plain `{id}`, not a bound model, so the middleware passes the class name and
 * Laravel drops it. These are role checks, and a role does not depend on which
 * row is being touched. A rule that does -- "a user may edit themselves" -- needs
 * the loaded model, so it belongs in the service, with the caller passed in.
 */
class UserPolicy
{
    /**
     * An administrator may do everything the API offers, so nothing below is
     * consulted for them. Returning null falls through to the methods.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->role === UserRole::Admin ? true : null;
    }

    /**
     * Anyone signed in may read the list.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Anyone signed in may read a single account.
     */
    public function view(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user): bool
    {
        return false;
    }

    /**
     * Resending an activation email sends mail to somebody else's address, so
     * it stays with the administrators along with the rest of the writes.
     */
    public function resendActivation(User $user): bool
    {
        return false;
    }

    /**
     * Covers both trails -- the audit log and the login attempts. They have the
     * same audience and the same sensitivity, and splitting the ability would
     * only invite one of them to be opened up by accident.
     *
     * An administrator's business and nobody else's: a plain user reading it
     * would learn who has been looking at whose account, and from where.
     */
    public function viewLogs(User $user): bool
    {
        return false;
    }

    /**
     * Whether the caller may hand out roles at all. Whether they may hand out
     * this particular one -- their own -- is UpdateUserRoleService's business,
     * because it depends on who is being changed.
     */
    public function changeRole(User $user): bool
    {
        return false;
    }

    /**
     * The one write a plain user may make. This says only that they may change
     * *an* avatar; whose they may change is AuthorizeAvatarChangeService's
     * business, because the answer depends on the target row and these methods
     * never see one.
     */
    public function changeAvatar(User $user): bool
    {
        return true;
    }

    public function deactivate(User $user): bool
    {
        return false;
    }

    public function reactivate(User $user): bool
    {
        return false;
    }
}

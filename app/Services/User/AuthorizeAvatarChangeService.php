<?php

namespace App\Services\User;

use App\Enums\UserRole;
use App\Exceptions\CannotChangeAnotherUsersAvatarException;
use App\Models\User;

/**
 * Who may set or remove which avatar.
 *
 * Its own class because both avatar use cases ask the same question, and a rule
 * copied into two services is a rule that eventually differs between them.
 *
 * UserPolicy cannot answer this one: its methods take only the caller, because
 * the routes carry a plain `{id}` rather than a bound model. The answer here
 * depends on the target row, so it belongs in the service layer -- with the
 * caller handed in by the controller, so services still never call auth().
 */
class AuthorizeAvatarChangeService
{
    /**
     * @throws CannotChangeAnotherUsersAvatarException when a plain user aims at somebody else
     */
    public function handle(User $caller, User $target): void
    {
        if ($caller->role === UserRole::Admin) {
            return;
        }

        if ($caller->id !== $target->id) {
            throw new CannotChangeAnotherUsersAvatarException;
        }
    }
}

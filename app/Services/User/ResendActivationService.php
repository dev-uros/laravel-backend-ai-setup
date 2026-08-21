<?php

namespace App\Services\User;

use App\Data\ActorData;
use App\Exceptions\AccountDeactivatedException;
use App\Exceptions\UserNotFoundException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class ResendActivationService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly SendActivationLinkService $activationLink,
    ) {}

    /**
     * Send the activation email again, for a user who never received it.
     *
     * @throws UserNotFoundException when no user has that id
     * @throws AccountDeactivatedException when the account is switched off
     */
    public function handle(int $id, ActorData $actor): User
    {
        $user = $this->users->findById($id) ?? throw new UserNotFoundException;

        /**
         * A link the recipient could not use anyway: activating a deactivated
         * account is refused too. Said plainly because only an administrator
         * reaches this endpoint, and the honest answer is "switch it back on
         * first".
         */
        if (! $user->isActive()) {
            throw new AccountDeactivatedException;
        }

        $this->activationLink->handle($user, $actor);

        return $user;
    }
}

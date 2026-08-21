<?php

namespace App\Services\User;

use App\Data\ActorData;
use App\Enums\UserLogEvent;
use App\Exceptions\CannotDeactivateOwnAccountException;
use App\Exceptions\UserNotFoundException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\UserLog\RecordUserLogService;
use Illuminate\Support\Facades\DB;

class DeactivateUserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RecordUserLogService $recordLog,
    ) {}

    /**
     * Switch an account off.
     *
     * Every token the account holds is revoked in the same transaction, so the
     * user is out on their next request rather than whenever their token would
     * have expired -- which, by default, is never. EnsureAccountIsActive is the
     * second lock behind this one.
     *
     * Deactivating an account that is already off is not an error: it leaves the
     * original timestamp alone and revokes anything that has appeared since.
     *
     * The caller is passed in rather than read from auth(), so this works from a
     * command or a job the same way it does from a request.
     *
     * @throws UserNotFoundException when no user has that id
     * @throws CannotDeactivateOwnAccountException when the caller is the target
     */
    public function handle(int $id, ActorData $actor): User
    {
        $user = $this->users->findById($id) ?? throw new UserNotFoundException;

        /**
         * Refusing self-deactivation is what keeps an active administrator in
         * place: only an active one gets this far, and they are still active
         * afterwards. Same shape as the rule in UpdateUserRoleService, and the
         * reason neither needs a "last administrator" count.
         */
        if ($user->id === $actor->id) {
            throw new CannotDeactivateOwnAccountException;
        }

        return DB::transaction(function () use ($user, $actor): User {
            $user->tokens()->delete();

            $deactivated = $this->users->deactivate($user);

            $this->recordLog->handle(
                $deactivated,
                UserLogEvent::Deactivated,
                $actor,
                ['deactivated_at' => null],
                ['deactivated_at' => $deactivated->deactivated_at],
            );

            return $deactivated;
        });
    }
}

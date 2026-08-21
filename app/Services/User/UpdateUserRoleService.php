<?php

namespace App\Services\User;

use App\Data\ActorData;
use App\Data\User\UpdateUserRoleData;
use App\Enums\UserLogEvent;
use App\Exceptions\CannotChangeOwnRoleException;
use App\Exceptions\UserNotFoundException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\UserLog\RecordUserLogService;
use Illuminate\Support\Facades\DB;

class UpdateUserRoleService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RecordUserLogService $recordLog,
    ) {}

    /**
     * Give a user a different role.
     *
     * The caller is passed in rather than read from auth(), so this works from a
     * command or a job the same way it does from a request.
     *
     * Tokens are deliberately left alone: the policy reads the role out of the
     * database on every request, so a demotion takes effect on the next one
     * without signing the user out of a session they are still entitled to.
     *
     * @throws UserNotFoundException when no user has that id
     * @throws CannotChangeOwnRoleException when the caller is the target
     */
    public function handle(int $id, UpdateUserRoleData $data, ActorData $actor): User
    {
        $user = $this->users->findById($id) ?? throw new UserNotFoundException;

        /**
         * Refusing self-changes is also what keeps the last administrator from
         * disappearing: the only way to demote one is for another to do it, and
         * that other one is still an administrator afterwards.
         */
        if ($user->id === $actor->id) {
            throw new CannotChangeOwnRoleException;
        }

        $before = $user->role;

        return DB::transaction(function () use ($user, $data, $actor, $before): User {
            $updated = $this->users->updateRole($user, $data->role);

            $this->recordLog->handle(
                $updated,
                UserLogEvent::RoleChanged,
                $actor,
                ['role' => $before],
                ['role' => $updated->role],
            );

            return $updated;
        });
    }
}

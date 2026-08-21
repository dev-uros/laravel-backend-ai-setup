<?php

namespace App\Services\User;

use App\Data\ActorData;
use App\Enums\UserLogEvent;
use App\Exceptions\UserNotFoundException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\UserLog\RecordUserLogService;
use Illuminate\Support\Facades\DB;

class ReactivateUserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RecordUserLogService $recordLog,
    ) {}

    /**
     * Switch an account back on.
     *
     * The user logs in again to get a token: the ones they held were revoked
     * when the account was switched off, and handing those back would defeat
     * the point of revoking them.
     *
     * @throws UserNotFoundException when no user has that id
     */
    public function handle(int $id, ActorData $actor): User
    {
        $user = $this->users->findById($id) ?? throw new UserNotFoundException;

        $before = $user->deactivated_at;

        return DB::transaction(function () use ($user, $actor, $before): User {
            $reactivated = $this->users->reactivate($user);

            $this->recordLog->handle(
                $reactivated,
                UserLogEvent::Reactivated,
                $actor,
                ['deactivated_at' => $before],
                ['deactivated_at' => null],
            );

            return $reactivated;
        });
    }
}

<?php

namespace App\Services\User;

use App\Data\ActorData;
use App\Data\User\UpdateUserData;
use App\Enums\UserLogEvent;
use App\Exceptions\UserNotFoundException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\UserLog\RecordUserLogService;
use Illuminate\Support\Facades\DB;

class UpdateUserService
{
    /**
     * The fields this use case can change, and therefore the only ones its
     * trail entry ever mentions.
     *
     * @var array<int, string>
     */
    private const TRACKED = ['name', 'surname', 'email'];

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RecordUserLogService $recordLog,
    ) {}

    /**
     * Update a user's details.
     *
     * @throws UserNotFoundException when no user has that id
     */
    public function handle(int $id, UpdateUserData $data, ActorData $actor): User
    {
        $user = $this->users->findById($id) ?? throw new UserNotFoundException;

        $before = $user->only(self::TRACKED);

        return DB::transaction(function () use ($user, $data, $actor, $before): User {
            $updated = $this->users->update($user, $data);

            $after = $updated->only(self::TRACKED);

            /**
             * Only what actually moved. Saving a form without touching anything
             * is not a change, and an entry saying so would bury the ones that
             * are.
             */
            $changed = array_keys(array_diff_assoc($after, $before));

            if ($changed !== []) {
                $this->recordLog->handle(
                    $updated,
                    UserLogEvent::Updated,
                    $actor,
                    array_intersect_key($before, array_flip($changed)),
                    array_intersect_key($after, array_flip($changed)),
                );
            }

            return $updated;
        });
    }
}

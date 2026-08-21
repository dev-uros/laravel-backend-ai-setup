<?php

namespace App\Repositories\Contracts;

use App\Data\ActorData;
use App\Enums\UserLogEvent;
use App\Models\User;
use App\Models\UserLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface UserLogRepositoryInterface
{
    /**
     * Append one entry to a user's trail.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public function record(
        User $user,
        UserLogEvent $event,
        ActorData $actor,
        array $oldValues,
        array $newValues,
    ): UserLog;

    /**
     * A user's trail, newest first.
     *
     * @return LengthAwarePaginator<int, UserLog>
     */
    public function paginateForUser(int $userId, int $perPage): LengthAwarePaginator;
}

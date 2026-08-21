<?php

namespace App\Repositories;

use App\Data\ActorData;
use App\Enums\UserLogEvent;
use App\Models\User;
use App\Models\UserLog;
use App\Repositories\Contracts\UserLogRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserLogRepository implements UserLogRepositoryInterface
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
    ): UserLog {
        return UserLog::query()->create([
            'user_id' => $user->id,
            'actor_id' => $actor->id,
            'event' => $event,
            'old_values' => $oldValues === [] ? null : $oldValues,
            'new_values' => $newValues === [] ? null : $newValues,
            'ip_address' => $actor->ipAddress,
            'user_agent' => $actor->userAgent,
        ]);
    }

    /**
     * A user's trail, newest first.
     *
     * The id breaks ties: several entries can share a timestamp to the second,
     * and without it the order of those is whatever Postgres feels like.
     *
     * @return LengthAwarePaginator<int, UserLog>
     */
    public function paginateForUser(int $userId, int $perPage): LengthAwarePaginator
    {
        return UserLog::query()
            ->where('user_id', $userId)
            ->with('actor')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}

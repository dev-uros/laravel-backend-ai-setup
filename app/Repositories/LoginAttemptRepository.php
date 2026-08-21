<?php

namespace App\Repositories;

use App\Data\ActorData;
use App\Enums\LoginOutcome;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Repositories\Contracts\LoginAttemptRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class LoginAttemptRepository implements LoginAttemptRepositoryInterface
{
    /**
     * Record one attempt. The user is null when the address matched nobody.
     */
    public function record(?User $user, string $email, LoginOutcome $outcome, ActorData $actor): LoginAttempt
    {
        return LoginAttempt::query()->create([
            'user_id' => $user?->id,
            'email' => $email,
            'outcome' => $outcome,
            'ip_address' => $actor->ipAddress,
            'user_agent' => $actor->userAgent,
        ]);
    }

    /**
     * A user's attempts, newest first.
     *
     * The id breaks ties: a handful of attempts can share a timestamp to the
     * second, and without it their order is whatever Postgres feels like.
     *
     * @return LengthAwarePaginator<int, LoginAttempt>
     */
    public function paginateForUser(int $userId, int $perPage): LengthAwarePaginator
    {
        return LoginAttempt::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }
}

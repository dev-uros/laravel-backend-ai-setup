<?php

namespace App\Repositories\Contracts;

use App\Data\ActorData;
use App\Enums\LoginOutcome;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface LoginAttemptRepositoryInterface
{
    /**
     * Record one attempt. The user is null when the address matched nobody.
     */
    public function record(?User $user, string $email, LoginOutcome $outcome, ActorData $actor): LoginAttempt;

    /**
     * A user's attempts, newest first.
     *
     * @return LengthAwarePaginator<int, LoginAttempt>
     */
    public function paginateForUser(int $userId, int $perPage): LengthAwarePaginator;
}

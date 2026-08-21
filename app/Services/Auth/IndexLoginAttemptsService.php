<?php

namespace App\Services\Auth;

use App\Exceptions\UserNotFoundException;
use App\Models\LoginAttempt;
use App\Repositories\Contracts\LoginAttemptRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexLoginAttemptsService
{
    /**
     * Attempts per page.
     */
    private const PER_PAGE = 25;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly LoginAttemptRepositoryInterface $attempts,
    ) {}

    /**
     * A user's login attempts, newest first.
     *
     * Attempts on addresses that match nobody are not reachable here -- they
     * belong to no account. They are in the table, for whoever is looking into
     * a sweep; this endpoint answers "what happened to this account".
     *
     * @return LengthAwarePaginator<int, LoginAttempt>
     *
     * @throws UserNotFoundException when no user has that id
     */
    public function handle(int $id): LengthAwarePaginator
    {
        $user = $this->users->findById($id) ?? throw new UserNotFoundException;

        return $this->attempts->paginateForUser($user->id, self::PER_PAGE);
    }
}

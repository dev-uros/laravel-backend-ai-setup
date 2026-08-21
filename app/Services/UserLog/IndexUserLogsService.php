<?php

namespace App\Services\UserLog;

use App\Exceptions\UserNotFoundException;
use App\Models\UserLog;
use App\Repositories\Contracts\UserLogRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexUserLogsService
{
    /**
     * Entries per page. Paginated unlike the users list, because a trail only
     * grows -- an account a support agent has been through a few times can have
     * hundreds of entries.
     */
    private const PER_PAGE = 25;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserLogRepositoryInterface $logs,
    ) {}

    /**
     * A user's audit trail, newest first.
     *
     * The user is looked up first so an unknown id answers 404 rather than an
     * empty page, which would read as "nothing ever happened to this account".
     *
     * @return LengthAwarePaginator<int, UserLog>
     *
     * @throws UserNotFoundException when no user has that id
     */
    public function handle(int $id): LengthAwarePaginator
    {
        $user = $this->users->findById($id) ?? throw new UserNotFoundException;

        return $this->logs->paginateForUser($user->id, self::PER_PAGE);
    }
}

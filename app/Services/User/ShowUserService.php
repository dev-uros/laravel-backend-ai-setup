<?php

namespace App\Services\User;

use App\Exceptions\UserNotFoundException;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;

class ShowUserService
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    /**
     * A single user.
     *
     * @throws UserNotFoundException when no user has that id
     */
    public function handle(int $id): User
    {
        return $this->users->findById($id) ?? throw new UserNotFoundException;
    }
}

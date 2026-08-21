<?php

namespace App\Services\User;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class IndexUsersService
{
    public function __construct(private readonly UserRepositoryInterface $users) {}

    /**
     * Every user, ordered by name and then surname.
     *
     * Takes no arguments: listing users needs no input, so there is no DTO.
     *
     * @return Collection<int, User>
     */
    public function handle(): Collection
    {
        return $this->users->allSortedByName();
    }
}

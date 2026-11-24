<?php

namespace App\Services\MobileUser;

use App\Exceptions\MobileAccountNotFoundException;
use App\Models\MobileUser;
use App\Repositories\Contracts\MobileUserRepositoryInterface;

class ShowMobileUserService
{
    public function __construct(private readonly MobileUserRepositoryInterface $mobileUsers) {}

    /**
     * A live account, read fresh. Deleted accounts are not found.
     *
     * @throws MobileAccountNotFoundException
     */
    public function handle(int $id): MobileUser
    {
        return $this->mobileUsers->findById($id) ?? throw MobileAccountNotFoundException::account();
    }
}

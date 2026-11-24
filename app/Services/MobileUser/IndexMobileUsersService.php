<?php

namespace App\Services\MobileUser;

use App\Data\MobileUser\IndexMobileUsersData;
use App\Models\MobileUser;
use App\Repositories\Contracts\MobileUserRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexMobileUsersService
{
    public function __construct(private readonly MobileUserRepositoryInterface $mobileUsers) {}

    /**
     * The external app's accounts for the admin, newest first, 25 a page.
     * Deleted accounts are not listed -- there is nobody left to describe.
     *
     * @return LengthAwarePaginator<int, MobileUser>
     */
    public function handle(IndexMobileUsersData $data): LengthAwarePaginator
    {
        return $this->mobileUsers->paginate($data);
    }
}

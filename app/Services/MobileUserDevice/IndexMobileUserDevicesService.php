<?php

namespace App\Services\MobileUserDevice;

use App\Exceptions\MobileAccountNotFoundException;
use App\Models\MobileUserDevice;
use App\Repositories\Contracts\MobileUserDeviceRepositoryInterface;
use App\Services\MobileUser\ShowMobileUserService;
use Illuminate\Support\Collection;

class IndexMobileUserDevicesService
{
    public function __construct(
        private readonly ShowMobileUserService $showUser,
        private readonly MobileUserDeviceRepositoryInterface $devices,
    ) {}

    /**
     * Every phone an account has signed in on, most recently seen first. Not
     * paginated: a person has a handful.
     *
     * @return Collection<int, MobileUserDevice>
     *
     * @throws MobileAccountNotFoundException
     */
    public function handle(int $mobileUserId): Collection
    {
        return $this->devices->listFor($this->showUser->handle($mobileUserId)->id);
    }
}

<?php

namespace App\Services\MobileUserDevice;

use App\Exceptions\MobileUserDeviceNotFoundException;
use App\Models\MobileUserDevice;
use App\Repositories\Contracts\MobileUserDeviceRepositoryInterface;

class ShowMobileUserDeviceService
{
    public function __construct(private readonly MobileUserDeviceRepositoryInterface $devices) {}

    /**
     * The phone the calling session is on, looked up by the session, never by
     * an id from the request.
     *
     * @throws MobileUserDeviceNotFoundException
     */
    public function handle(int $tokenId): MobileUserDevice
    {
        return $this->devices->findBySession($tokenId) ?? throw new MobileUserDeviceNotFoundException;
    }
}

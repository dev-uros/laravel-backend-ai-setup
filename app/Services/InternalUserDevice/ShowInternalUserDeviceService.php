<?php

namespace App\Services\InternalUserDevice;

use App\Exceptions\InternalUserDeviceNotFoundException;
use App\Models\InternalUserDevice;
use App\Repositories\Contracts\InternalUserDeviceRepositoryInterface;

class ShowInternalUserDeviceService
{
    public function __construct(private readonly InternalUserDeviceRepositoryInterface $devices) {}

    /**
     * The phone the calling session is on.
     *
     * Looked up by the session, never by an id from the request, so a caller
     * can only ever reach their own phone -- there is nothing to guess.
     *
     * @throws InternalUserDeviceNotFoundException when the session registered no phone
     */
    public function handle(int $tokenId): InternalUserDevice
    {
        return $this->devices->findBySession($tokenId) ?? throw new InternalUserDeviceNotFoundException;
    }
}

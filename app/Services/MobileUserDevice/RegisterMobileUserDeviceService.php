<?php

namespace App\Services\MobileUserDevice;

use App\Data\MobileUserDevice\RegisterMobileUserDeviceData;
use App\Models\MobileUserDevice;
use App\Repositories\Contracts\MobileUserDeviceRepositoryInterface;
use Illuminate\Support\Facades\DB;

class RegisterMobileUserDeviceService
{
    public function __construct(private readonly MobileUserDeviceRepositoryInterface $devices) {}

    /**
     * Record the phone a session is on: at sign-in, and on every launch after.
     * The same rules as `RegisterInternalUserDeviceService`: keyed on the user
     * and the phone's id, bound to the session, and a push token is first
     * taken off every other row.
     */
    public function handle(int $mobileUserId, int $tokenId, RegisterMobileUserDeviceData $data): MobileUserDevice
    {
        return DB::transaction(function () use ($mobileUserId, $tokenId, $data): MobileUserDevice {
            $device = $this->devices->register($mobileUserId, $tokenId, $data);

            if (! $data->updatesPushToken) {
                return $device;
            }

            if ($data->pushToken !== null) {
                $this->devices->releasePushToken($data->pushToken, $device->id);
            }

            return $this->devices->setPushToken($device, $data->pushToken);
        });
    }
}

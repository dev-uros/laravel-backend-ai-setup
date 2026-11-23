<?php

namespace App\Services\InternalUserDevice;

use App\Data\InternalUserDevice\RegisterInternalUserDeviceData;
use App\Models\InternalUserDevice;
use App\Repositories\Contracts\InternalUserDeviceRepositoryInterface;
use Illuminate\Support\Facades\DB;

class RegisterInternalUserDeviceService
{
    public function __construct(private readonly InternalUserDeviceRepositoryInterface $devices) {}

    /**
     * Record the phone a session is on: at sign-in, and on every launch after.
     *
     * The row is keyed on the user and the phone's own id, so signing in again
     * on the same phone updates it rather than adding another. It is bound to
     * the session, which is what lets a logout -- or anything else that
     * deletes the token -- silence the phone without being told to.
     *
     * The push token is written only when the caller sent the key. A value is
     * first taken off every other row: the same phone signed in by somebody
     * else would otherwise keep receiving the previous user's notifications.
     */
    public function handle(int $userId, int $tokenId, RegisterInternalUserDeviceData $data): InternalUserDevice
    {
        return DB::transaction(function () use ($userId, $tokenId, $data): InternalUserDevice {
            $device = $this->devices->register($userId, $tokenId, $data);

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

<?php

namespace App\Repositories;

use App\Data\InternalUserDevice\RegisterInternalUserDeviceData;
use App\Models\InternalUserDevice;
use App\Repositories\Contracts\InternalUserDeviceRepositoryInterface;

class InternalUserDeviceRepository implements InternalUserDeviceRepositoryInterface
{
    public function findBySession(int $tokenId): ?InternalUserDevice
    {
        return InternalUserDevice::query()
            ->where('personal_access_token_id', $tokenId)
            ->first();
    }

    public function register(int $userId, int $tokenId, RegisterInternalUserDeviceData $data): InternalUserDevice
    {
        InternalUserDevice::query()
            ->where('personal_access_token_id', $tokenId)
            ->where(fn ($query) => $query->where('user_id', '!=', $userId)->orWhere('device_id', '!=', $data->deviceId))
            ->update(['personal_access_token_id' => null]);

        $device = InternalUserDevice::query()
            ->where('user_id', $userId)
            ->where('device_id', $data->deviceId)
            ->first() ?? new InternalUserDevice;

        $device->forceFill([
            'user_id' => $userId,
            'personal_access_token_id' => $tokenId,
            ...$data->toAttributes(),
            'last_seen_at' => now(),
        ])->save();

        return $device;
    }

    public function releasePushToken(string $token, int $exceptDeviceId): void
    {
        InternalUserDevice::query()
            ->where('expo_push_notification_token', $token)
            ->whereKeyNot($exceptDeviceId)
            ->update(['expo_push_notification_token' => null]);
    }

    public function setPushToken(InternalUserDevice $device, ?string $token): InternalUserDevice
    {
        $device->forceFill(['expo_push_notification_token' => $token])->save();

        return $device;
    }

    /**
     * @param  array<int, string>  $tokens
     */
    public function forgetPushTokens(array $tokens): int
    {
        if ($tokens === []) {
            return 0;
        }

        return InternalUserDevice::query()
            ->whereIn('expo_push_notification_token', $tokens)
            ->update(['expo_push_notification_token' => null]);
    }
}

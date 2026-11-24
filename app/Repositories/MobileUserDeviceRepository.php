<?php

namespace App\Repositories;

use App\Data\MobileUserDevice\RegisterMobileUserDeviceData;
use App\Models\MobileUserDevice;
use App\Repositories\Contracts\MobileUserDeviceRepositoryInterface;
use Illuminate\Support\Collection;

class MobileUserDeviceRepository implements MobileUserDeviceRepositoryInterface
{
    public function findBySession(int $tokenId): ?MobileUserDevice
    {
        return MobileUserDevice::query()
            ->where('personal_access_token_id', $tokenId)
            ->first();
    }

    public function register(int $mobileUserId, int $tokenId, RegisterMobileUserDeviceData $data): MobileUserDevice
    {
        MobileUserDevice::query()
            ->where('personal_access_token_id', $tokenId)
            ->where(fn ($query) => $query->where('mobile_user_id', '!=', $mobileUserId)->orWhere('device_id', '!=', $data->deviceId))
            ->update(['personal_access_token_id' => null]);

        $device = MobileUserDevice::query()
            ->where('mobile_user_id', $mobileUserId)
            ->where('device_id', $data->deviceId)
            ->first() ?? new MobileUserDevice;

        $device->forceFill([
            'mobile_user_id' => $mobileUserId,
            'personal_access_token_id' => $tokenId,
            ...$data->toAttributes(),
            'last_seen_at' => now(),
        ])->save();

        return $device;
    }

    public function releasePushToken(string $token, int $exceptDeviceId): void
    {
        MobileUserDevice::query()
            ->where('expo_push_notification_token', $token)
            ->whereKeyNot($exceptDeviceId)
            ->update(['expo_push_notification_token' => null]);
    }

    public function setPushToken(MobileUserDevice $device, ?string $token): MobileUserDevice
    {
        $device->forceFill(['expo_push_notification_token' => $token])->save();

        return $device;
    }

    public function forgetPushTokenOfSession(int $tokenId): void
    {
        MobileUserDevice::query()
            ->where('personal_access_token_id', $tokenId)
            ->update(['expo_push_notification_token' => null]);
    }

    /**
     * @param  array<int, string>  $tokens
     */
    public function forgetPushTokens(array $tokens): int
    {
        if ($tokens === []) {
            return 0;
        }

        return MobileUserDevice::query()
            ->whereIn('expo_push_notification_token', $tokens)
            ->update(['expo_push_notification_token' => null]);
    }

    public function deleteAllFor(int $mobileUserId): void
    {
        MobileUserDevice::query()->where('mobile_user_id', $mobileUserId)->delete();
    }

    /**
     * @return Collection<int, MobileUserDevice>
     */
    public function listFor(int $mobileUserId): Collection
    {
        return MobileUserDevice::query()
            ->where('mobile_user_id', $mobileUserId)
            ->latest('last_seen_at')
            ->get();
    }
}

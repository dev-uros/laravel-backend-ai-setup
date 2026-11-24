<?php

namespace App\Repositories\Contracts;

use App\Data\MobileUserDevice\RegisterMobileUserDeviceData;
use App\Models\MobileUserDevice;
use Illuminate\Support\Collection;

interface MobileUserDeviceRepositoryInterface
{
    /** The phone a session is on, or null when that session registered none. */
    public function findBySession(int $tokenId): ?MobileUserDevice;

    /**
     * Create or update the user's row for this phone, bind it to the session
     * and stamp `last_seen_at`. The push token is not touched.
     */
    public function register(int $mobileUserId, int $tokenId, RegisterMobileUserDeviceData $data): MobileUserDevice;

    /** Take a push token off every row except the given one. */
    public function releasePushToken(string $token, int $exceptDeviceId): void;

    public function setPushToken(MobileUserDevice $device, ?string $token): MobileUserDevice;

    /**
     * Clear the push token of the phone a session is on -- signing out stops
     * the notifications on that phone at once.
     */
    public function forgetPushTokenOfSession(int $tokenId): void;

    /**
     * Clear these push tokens wherever they are stored; the number of rows
     * that held one.
     *
     * @param  array<int, string>  $tokens
     */
    public function forgetPushTokens(array $tokens): int;

    public function deleteAllFor(int $mobileUserId): void;

    /**
     * @return Collection<int, MobileUserDevice>
     */
    public function listFor(int $mobileUserId): Collection;
}

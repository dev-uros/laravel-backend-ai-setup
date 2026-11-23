<?php

namespace App\Repositories\Contracts;

use App\Data\InternalUserDevice\RegisterInternalUserDeviceData;
use App\Models\InternalUserDevice;

interface InternalUserDeviceRepositoryInterface
{
    /**
     * The phone a session is on, or null when that session never registered
     * one (the web app, or a token minted before the app sent its device).
     */
    public function findBySession(int $tokenId): ?InternalUserDevice;

    /**
     * Create or update the user's row for this phone, bind it to the session
     * and stamp `last_seen_at`. The push token is not touched.
     *
     * Any other row still bound to the same session is released first: a
     * token is on one phone, and the unique index would refuse the save.
     */
    public function register(int $userId, int $tokenId, RegisterInternalUserDeviceData $data): InternalUserDevice;

    /**
     * Take a push token off every row except the given one. A phone handed
     * from one user to another keeps its token, and it must stop reaching the
     * first user.
     */
    public function releasePushToken(string $token, int $exceptDeviceId): void;

    /**
     * Store the push token, or null to switch notifications off.
     */
    public function setPushToken(InternalUserDevice $device, ?string $token): InternalUserDevice;

    /**
     * Drop tokens Expo reported as no longer registered. Returns how many rows
     * changed.
     *
     * @param  array<int, string>  $tokens
     */
    public function forgetPushTokens(array $tokens): int;
}

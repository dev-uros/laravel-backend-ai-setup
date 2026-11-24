<?php

namespace App\Services\MobileAuth;

use App\Models\MobileUser;
use App\Repositories\Contracts\MobileUserRepositoryInterface;

/**
 * Trade the phone's authorization code for a refresh token and keep it
 * (encrypted), so a deleted account's Apple authorisation can be revoked.
 *
 * Called **after** a sign-in or registration has committed: a refused sign-in
 * never spends the code, so after a 404 the app hands the same one to
 * registration. Never fails the sign-in -- the person is already in.
 */
class StoreAppleRefreshTokenService
{
    public function __construct(
        private readonly ExchangeAppleAuthorizationCodeService $exchange,
        private readonly MobileUserRepositoryInterface $mobileUsers,
    ) {}

    public function handle(MobileUser $user, string $appleId, string $clientId, ?string $authorizationCode): void
    {
        $refreshToken = $this->exchange->handle($user->id, $appleId, $clientId, $authorizationCode);

        /**
         * The bundle ID is kept even when the trade failed: deleting the
         * account trades a fresh code, and Apple wants it sent under the
         * bundle ID it was issued to.
         */
        $this->mobileUsers->updateAppleCredentials($user, $clientId, $refreshToken);
    }
}

<?php

namespace App\Services\MobileAuth;

use App\Support\Apple\AppleTokenClientInterface;
use App\Support\Apple\AppleTokenRequestFailedException;
use Illuminate\Support\Facades\Log;

/**
 * Trade the phone's Sign in with Apple authorization code for a refresh token.
 * Shared by signing in, registering and deleting the account.
 *
 * **Never throws.** Whatever the caller is doing has already been decided, so
 * a failed trade is a warning in the log and `null`. That also covers a
 * deployment without `APPLE_TEAM_ID`/`APPLE_KEY_ID`/`APPLE_PRIVATE_KEY_BASE64`:
 * sign-in works, revocation is simply not possible.
 *
 * The token is handed back only when Apple's answer names the Apple ID the
 * account holds -- otherwise somebody could pair their account with another
 * person's code, and revoking one would revoke the other.
 */
class ExchangeAppleAuthorizationCodeService
{
    public function __construct(
        private readonly AppleTokenClientInterface $apple,
    ) {}

    public function handle(int $mobileUserId, string $appleId, string $clientId, ?string $authorizationCode): ?string
    {
        if ($authorizationCode === null || $authorizationCode === '') {
            return null;
        }

        try {
            $token = $this->apple->exchangeCode($authorizationCode, $clientId);
        } catch (AppleTokenRequestFailedException $exception) {
            Log::warning('apple authorization code not exchanged', [
                'mobile_user_id' => $mobileUserId,
                'reason' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($token->sub !== $appleId) {
            Log::warning('apple authorization code belongs to another apple id', ['mobile_user_id' => $mobileUserId]);

            return null;
        }

        return $token->refreshToken;
    }
}

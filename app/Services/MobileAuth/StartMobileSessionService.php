<?php

namespace App\Services\MobileAuth;

use App\Data\MobileAuth\AuthenticatedMobileUserData;
use App\Data\MobileAuth\SessionContextData;
use App\Exceptions\WrongAudienceException;
use App\Models\MobileUser;
use App\Repositories\Contracts\MobileUserRepositoryInterface;
use App\Services\MobileUserDevice\RegisterMobileUserDeviceService;
use Illuminate\Support\Facades\DB;

/**
 * Let somebody in whose account exists or has just been made: the one place
 * the external app is handed a token. Every way in -- code, Google, Apple,
 * registration -- ends here, so "a blocked account is not let in", the token's
 * name and the device binding cannot drift between them.
 *
 * The language the app is in is written to the account: it is the moment the
 * phone tells us what to write mail and pushes in, and it may have changed.
 */
class StartMobileSessionService
{
    public function __construct(
        private readonly MobileUserRepositoryInterface $mobileUsers,
        private readonly RegisterMobileUserDeviceService $registerDevice,
    ) {}

    /**
     * @throws WrongAudienceException when the account has been blocked
     */
    public function handle(MobileUser $user, SessionContextData $context): AuthenticatedMobileUserData
    {
        /**
         * Its own transaction, which nests inside the caller's: a blocked
         * account takes back whatever the caller wrote on the way here, such
         * as a Google account linked a moment ago.
         */
        return DB::transaction(function () use ($user, $context): AuthenticatedMobileUserData {
            if ($user->isBlocked()) {
                throw WrongAudienceException::blocked();
            }

            $user = $this->mobileUsers->updateLocale($user, $context->locale);
            $this->mobileUsers->touchLastSeen($user);

            $token = $user->createToken(MobileUser::TOKEN_NAME);

            if ($context->device !== null) {
                $this->registerDevice->handle($user->id, $token->accessToken->id, $context->device);
            }

            return new AuthenticatedMobileUserData($user, $token->plainTextToken);
        });
    }
}

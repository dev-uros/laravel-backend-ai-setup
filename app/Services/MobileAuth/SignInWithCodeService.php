<?php

namespace App\Services\MobileAuth;

use App\Data\MobileAuth\AuthenticatedMobileUserData;
use App\Data\MobileAuth\SignInWithCodeData;
use App\Exceptions\InvalidOtpCodeException;
use App\Exceptions\WrongAudienceException;
use App\Repositories\Contracts\MobileUserRepositoryInterface;

/**
 * Turn a code into a token.
 *
 * A blocked account is refused in `StartMobileSessionService` as well: asking
 * for a code already refuses one, but a code issued just before the block must
 * still be stopped here.
 */
class SignInWithCodeService
{
    public function __construct(
        private readonly VerifyOtpCodeService $verify,
        private readonly MobileUserRepositoryInterface $mobileUsers,
        private readonly StartMobileSessionService $startSession,
    ) {}

    /**
     * @throws InvalidOtpCodeException
     * @throws WrongAudienceException when the account has been blocked
     */
    public function handle(SignInWithCodeData $data): AuthenticatedMobileUserData
    {
        /** Outside any transaction -- see `VerifyOtpCodeService`. */
        $this->verify->handle($data->email, $data->code);

        /**
         * The code was right but there is no account: it was a registration
         * code, or the account has since been deleted. The same answer as a
         * wrong code.
         */
        $user = $this->mobileUsers->findByEmail($data->email) ?? throw new InvalidOtpCodeException;

        return $this->startSession->handle($user, $data->context);
    }
}

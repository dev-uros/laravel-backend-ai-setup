<?php

namespace App\Services\MobileAuth;

use App\Data\MobileAuth\CodeRequestResultData;
use App\Data\MobileAuth\EmailCodeData;
use App\Exceptions\OtpResendTooSoonException;
use App\Exceptions\WrongAudienceException;
use App\Repositories\Contracts\MobileUserRepositoryInterface;

/**
 * Send somebody the code they sign in with, and say whether there was anybody
 * to send it to.
 *
 * **The answer says whether the address has an account** -- a decision taken
 * for this app: people type a new address into the sign-in form, and the app
 * takes them to registration instead of leaving them waiting for a code that
 * never comes. Registration has to say "that address is taken" anyway, so the
 * route throttles (per address and per IP) are what keeps a sweep slow.
 *
 * - **No account:** `accountExists: false`, nothing written, nothing mailed.
 * - **Blocked account:** 403, nothing mailed.
 * - **Account:** a code is issued and mailed (`IssueOtpCodeService`).
 */
class RequestSignInCodeService
{
    public function __construct(
        private readonly MobileUserRepositoryInterface $mobileUsers,
        private readonly IssueOtpCodeService $issueCode,
    ) {}

    /**
     * @throws WrongAudienceException when the account has been blocked
     * @throws OtpResendTooSoonException
     */
    public function handle(EmailCodeData $data): CodeRequestResultData
    {
        $user = $this->mobileUsers->findByEmail($data->email);

        if ($user === null) {
            return new CodeRequestResultData(accountExists: false, retryAfter: 0);
        }

        if ($user->isBlocked()) {
            throw WrongAudienceException::blocked();
        }

        return new CodeRequestResultData(
            accountExists: true,
            retryAfter: $this->issueCode->handle($data->email, $data->locale, $user),
        );
    }
}

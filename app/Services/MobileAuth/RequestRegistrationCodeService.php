<?php

namespace App\Services\MobileAuth;

use App\Data\MobileAuth\CodeRequestResultData;
use App\Data\MobileAuth\EmailCodeData;
use App\Exceptions\MobileAccountAlreadyExistsException;
use App\Exceptions\OtpResendTooSoonException;
use App\Repositories\Contracts\MobileUserRepositoryInterface;

/**
 * A code for an address that does **not** have an account yet. The request
 * already refused a taken address or handle with a 422; this checks the
 * address again because the two can race.
 */
class RequestRegistrationCodeService
{
    public function __construct(
        private readonly MobileUserRepositoryInterface $mobileUsers,
        private readonly IssueOtpCodeService $issueCode,
    ) {}

    /**
     * @throws MobileAccountAlreadyExistsException
     * @throws OtpResendTooSoonException
     */
    public function handle(EmailCodeData $data): CodeRequestResultData
    {
        if ($this->mobileUsers->findByEmail($data->email) !== null) {
            throw new MobileAccountAlreadyExistsException;
        }

        return new CodeRequestResultData(
            accountExists: false,
            retryAfter: $this->issueCode->handle($data->email, $data->locale),
        );
    }
}

<?php

namespace App\Services\MobileAuth;

use App\Data\MobileAuth\AuthenticatedMobileUserData;
use App\Data\MobileAuth\RegisterWithCodeData;
use App\Exceptions\InvalidOtpCodeException;
use App\Exceptions\MobileAccountAlreadyExistsException;

/**
 * Make an account, in one step, at the moment the code comes back. The code
 * is what proves the address.
 */
class RegisterWithCodeService
{
    public function __construct(
        private readonly VerifyOtpCodeService $verify,
        private readonly CreateMobileAccountService $createAccount,
    ) {}

    /**
     * @throws InvalidOtpCodeException
     * @throws MobileAccountAlreadyExistsException
     */
    public function handle(RegisterWithCodeData $data): AuthenticatedMobileUserData
    {
        /** Outside any transaction -- see `VerifyOtpCodeService`. */
        $this->verify->handle($data->email, $data->code);

        return $this->createAccount->handle($data->email, $data->profile, $data->context);
    }
}

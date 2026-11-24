<?php

namespace App\Services\MobileAuth;

use App\Data\MobileAuth\AuthenticatedMobileUserData;
use App\Data\MobileAuth\RegisterWithGoogleData;
use App\Exceptions\InvalidProviderTokenException;
use App\Exceptions\MobileAccountAlreadyExistsException;
use App\Exceptions\ProviderSignInUnavailableException;
use App\Repositories\Contracts\MobileUserRepositoryInterface;
use App\Support\Google\GoogleIdTokenVerifierInterface;

/**
 * Open an account with Google vouching for the address, instead of a mailed
 * code. An account that already exists -- by the address or by the Google
 * account -- is refused with a 409, not joined: linking belongs to signing in.
 */
class RegisterWithGoogleService
{
    public function __construct(
        private readonly GoogleIdTokenVerifierInterface $verifier,
        private readonly MobileUserRepositoryInterface $mobileUsers,
        private readonly CreateMobileAccountService $createAccount,
    ) {}

    /**
     * @throws InvalidProviderTokenException
     * @throws ProviderSignInUnavailableException
     * @throws MobileAccountAlreadyExistsException
     */
    public function handle(RegisterWithGoogleData $data): AuthenticatedMobileUserData
    {
        $identity = $this->verifier->verify($data->idToken);

        if ($this->mobileUsers->findByGoogleId($identity->sub) !== null
            || $this->mobileUsers->findByEmail($identity->email) !== null) {
            throw new MobileAccountAlreadyExistsException;
        }

        return $this->createAccount->handle($identity->email, $data->profile, $data->context, googleId: $identity->sub);
    }
}

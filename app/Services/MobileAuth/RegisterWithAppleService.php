<?php

namespace App\Services\MobileAuth;

use App\Data\MobileAuth\AuthenticatedMobileUserData;
use App\Data\MobileAuth\RegisterWithAppleData;
use App\Exceptions\InvalidProviderTokenException;
use App\Exceptions\MobileAccountAlreadyExistsException;
use App\Exceptions\ProviderSignInUnavailableException;
use App\Repositories\Contracts\MobileUserRepositoryInterface;
use App\Support\Apple\AppleIdTokenVerifierInterface;

/**
 * Open an account with Apple vouching for the address. A hidden relay address
 * is stored as it is: it is the address Apple vouches for, and Apple forwards
 * our mail to it once the sending domain is registered with Apple.
 */
class RegisterWithAppleService
{
    public function __construct(
        private readonly AppleIdTokenVerifierInterface $verifier,
        private readonly MobileUserRepositoryInterface $mobileUsers,
        private readonly CreateMobileAccountService $createAccount,
        private readonly StoreAppleRefreshTokenService $storeRefreshToken,
    ) {}

    /**
     * @throws InvalidProviderTokenException
     * @throws ProviderSignInUnavailableException
     * @throws MobileAccountAlreadyExistsException
     */
    public function handle(RegisterWithAppleData $data): AuthenticatedMobileUserData
    {
        $identity = $this->verifier->verify($data->idToken, $data->nonce);

        if ($this->mobileUsers->findByAppleId($identity->sub) !== null
            || $this->mobileUsers->findByEmail($identity->email) !== null) {
            throw new MobileAccountAlreadyExistsException;
        }

        $registered = $this->createAccount->handle($identity->email, $data->profile, $data->context, appleId: $identity->sub);

        $this->storeRefreshToken->handle($registered->user, $identity->sub, $identity->clientId, $data->authorizationCode);

        return $registered;
    }
}

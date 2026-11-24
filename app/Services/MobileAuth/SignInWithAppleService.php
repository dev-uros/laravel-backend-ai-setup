<?php

namespace App\Services\MobileAuth;

use App\Data\MobileAuth\AuthenticatedMobileUserData;
use App\Data\MobileAuth\SignInWithAppleData;
use App\Exceptions\InvalidProviderTokenException;
use App\Exceptions\MobileAccountNotFoundException;
use App\Exceptions\ProviderAccountNotLinkableException;
use App\Exceptions\ProviderSignInUnavailableException;
use App\Exceptions\WrongAudienceException;
use App\Models\MobileUser;
use App\Repositories\Contracts\MobileUserRepositoryInterface;
use App\Support\Apple\AppleIdTokenVerifier;
use App\Support\Apple\AppleIdTokenVerifierInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turn an Apple identity token into a token of ours.
 *
 * The same rules as `SignInWithGoogleService`, with Apple's domains: an
 * account found by its address is linked only when the address is iCloud
 * (`icloud.com`, `me.com`, `mac.com`). A hidden relay address never matches an
 * account opened another way, so the rule never meets one.
 */
class SignInWithAppleService
{
    /** The addresses Apple itself hands out. */
    private const APPLE_DOMAINS = ['icloud.com', 'me.com', 'mac.com'];

    public function __construct(
        private readonly AppleIdTokenVerifierInterface $verifier,
        private readonly MobileUserRepositoryInterface $mobileUsers,
        private readonly StartMobileSessionService $startSession,
        private readonly StoreAppleRefreshTokenService $storeRefreshToken,
    ) {}

    /**
     * @throws InvalidProviderTokenException
     * @throws ProviderSignInUnavailableException
     * @throws MobileAccountNotFoundException when neither the Apple ID nor its address has an account
     * @throws ProviderAccountNotLinkableException when the address has an account the Apple ID may not join
     * @throws WrongAudienceException when the account has been blocked
     */
    public function handle(SignInWithAppleData $data): AuthenticatedMobileUserData
    {
        $identity = $this->verifier->verify($data->idToken, $data->nonce);

        $linked = false;

        $signedIn = DB::transaction(function () use ($data, $identity, &$linked): AuthenticatedMobileUserData {
            $user = $this->mobileUsers->findByAppleId($identity->sub);

            if (! $user instanceof MobileUser) {
                $user = $this->linkByAddress($identity->sub, $identity->email);
                $linked = true;
            }

            return $this->startSession->handle($user, $data->context);
        });

        if ($linked) {
            Log::info('apple account linked', ['mobile_user_id' => $signedIn->user->id]);
        }

        /** After the commit, so a refused sign-in leaves the code unspent for registering. */
        $this->storeRefreshToken->handle($signedIn->user, $identity->sub, $identity->clientId, $data->authorizationCode);

        return $signedIn;
    }

    private function linkByAddress(string $appleId, string $email): MobileUser
    {
        $user = $this->mobileUsers->findByEmail($email)
            ?? throw MobileAccountNotFoundException::forProvider(AppleIdTokenVerifier::PROVIDER);

        if ($user->apple_id !== null || ! in_array(substr((string) strrchr($email, '@'), 1), self::APPLE_DOMAINS, true)) {
            throw new ProviderAccountNotLinkableException;
        }

        if (! $this->mobileUsers->linkAppleAccount($user, $appleId)) {
            return $this->mobileUsers->findByAppleId($appleId) ?? throw new ProviderAccountNotLinkableException;
        }

        return $user;
    }
}

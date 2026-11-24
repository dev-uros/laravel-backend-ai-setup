<?php

namespace App\Services\MobileAuth;

use App\Data\MobileAuth\AuthenticatedMobileUserData;
use App\Data\MobileAuth\SignInWithGoogleData;
use App\Exceptions\InvalidProviderTokenException;
use App\Exceptions\MobileAccountNotFoundException;
use App\Exceptions\ProviderAccountNotLinkableException;
use App\Exceptions\ProviderSignInUnavailableException;
use App\Exceptions\WrongAudienceException;
use App\Models\MobileUser;
use App\Repositories\Contracts\MobileUserRepositoryInterface;
use App\Support\Google\GoogleIdentityData;
use App\Support\Google\GoogleIdTokenVerifier;
use App\Support\Google\GoogleIdTokenVerifierInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turn a Google ID token into a token of ours.
 *
 * The account is looked for by the Google account first and by its address
 * second: `sub` never changes, the address on a Google account can. **An
 * account found by its address, with no Google account yet, is linked** --
 * somebody who registered with a code and now taps "Continue with Google"
 * gets in, and from then on either way works.
 *
 * Only where Google is authoritative for the address: a Gmail address, or a
 * Workspace account (`hd`). Anybody can open a Google account on an address at
 * another provider; Google checked it once, which does not prove the person
 * holds it today. Those get a 409 and sign in with a code.
 *
 * No account at all is a 404: the app takes the person to registration.
 */
class SignInWithGoogleService
{
    /** The addresses Google itself hands out. Any other domain is Google's only when the token names it in `hd`. */
    private const GOOGLE_DOMAINS = ['gmail.com', 'googlemail.com'];

    public function __construct(
        private readonly GoogleIdTokenVerifierInterface $verifier,
        private readonly MobileUserRepositoryInterface $mobileUsers,
        private readonly StartMobileSessionService $startSession,
    ) {}

    /**
     * @throws InvalidProviderTokenException
     * @throws ProviderSignInUnavailableException
     * @throws MobileAccountNotFoundException when neither the Google account nor its address has an account
     * @throws ProviderAccountNotLinkableException when the address has an account the Google account may not join
     * @throws WrongAudienceException when the account has been blocked
     */
    public function handle(SignInWithGoogleData $data): AuthenticatedMobileUserData
    {
        $identity = $this->verifier->verify($data->idToken);

        $linked = false;

        /** One transaction, so a blocked account takes the link back with it. */
        $signedIn = DB::transaction(function () use ($data, $identity, &$linked): AuthenticatedMobileUserData {
            $user = $this->mobileUsers->findByGoogleId($identity->sub);

            if (! $user instanceof MobileUser) {
                $user = $this->linkByAddress($identity);
                $linked = true;
            }

            return $this->startSession->handle($user, $data->context);
        });

        if ($linked) {
            Log::info('google account linked', ['mobile_user_id' => $signedIn->user->id]);
        }

        return $signedIn;
    }

    private function linkByAddress(GoogleIdentityData $identity): MobileUser
    {
        $user = $this->mobileUsers->findByEmail($identity->email)
            ?? throw MobileAccountNotFoundException::forProvider(GoogleIdTokenVerifier::PROVIDER);

        /** Not this Google account -- the lookup by `sub` would have found it -- so somebody else's. */
        if ($user->google_id !== null || ! $this->googleIsAuthoritative($identity)) {
            throw new ProviderAccountNotLinkableException;
        }

        /**
         * Conditional, so two sign-ins racing to link cannot overwrite each
         * other. Losing the race to the same Google account is still a
         * sign-in; losing it to another one is not.
         */
        if (! $this->mobileUsers->linkGoogleAccount($user, $identity->sub)) {
            return $this->mobileUsers->findByGoogleId($identity->sub) ?? throw new ProviderAccountNotLinkableException;
        }

        return $user;
    }

    private function googleIsAuthoritative(GoogleIdentityData $identity): bool
    {
        return $identity->hostedDomain !== null
            || in_array(substr((string) strrchr($identity->email, '@'), 1), self::GOOGLE_DOMAINS, true);
    }
}

<?php

namespace App\Support\Apple;

use App\Exceptions\InvalidProviderTokenException;
use App\Exceptions\ProviderSignInUnavailableException;
use App\Support\IdToken\IdTokenIssuer;
use App\Support\IdToken\IdTokenKeysUnavailableException;
use App\Support\IdToken\IdTokenRejectedException;
use App\Support\IdToken\JwksIdTokenDecoder;
use Illuminate\Support\Facades\Log;
use stdClass;

/**
 * Checks a Sign in with Apple identity token, offline, against Apple's
 * published keys.
 *
 * **No `.p8` key and no client secret**: those are for Apple's token endpoint
 * (`AppleTokenClient`), which checking an identity token never calls. `aud`
 * must be one of `services.apple_oauth.client_ids` -- the bundle IDs of the
 * app's three variants, because the phone signs in natively.
 *
 * **The nonce is checked.** The app makes a random value, hands Apple its
 * SHA-256 and sends us the raw value; the token must carry that hash. A token
 * lifted from somewhere else was issued against a nonce the thief does not
 * know.
 */
class AppleIdTokenVerifier implements AppleIdTokenVerifierInterface
{
    public const PROVIDER = 'Apple';

    private const KEYS_URL = 'https://appleid.apple.com/auth/keys';

    private const CACHE_PREFIX = 'apple-oauth';

    private const ISSUERS = ['https://appleid.apple.com'];

    public function __construct(
        private readonly JwksIdTokenDecoder $decoder,
    ) {}

    public function verify(string $idToken, string $nonce): AppleIdentityData
    {
        $issuer = new IdTokenIssuer(
            name: 'apple',
            keysUrl: self::KEYS_URL,
            cachePrefix: self::CACHE_PREFIX,
            issuers: self::ISSUERS,
            audiences: $this->clientIds(),
        );

        try {
            $claims = $this->decoder->decode($idToken, $issuer);
        } catch (IdTokenRejectedException $exception) {
            $this->refuse($exception->getMessage());
        } catch (IdTokenKeysUnavailableException) {
            throw new ProviderSignInUnavailableException(self::PROVIDER);
        }

        if (! is_string($claims->nonce ?? null) || ! hash_equals($claims->nonce, hash('sha256', $nonce))) {
            $this->refuse('nonce mismatch');
        }

        return $this->identity($claims);
    }

    /**
     * @return list<string>
     */
    private function clientIds(): array
    {
        $clientIds = config('services.apple_oauth.client_ids');

        if (! is_array($clientIds) || $clientIds === []) {
            Log::error('apple oauth client ids are not configured');

            throw new ProviderSignInUnavailableException(self::PROVIDER);
        }

        return array_values($clientIds);
    }

    private function identity(stdClass $claims): AppleIdentityData
    {
        /**
         * The app must ask for the `EMAIL` scope. Without an address there is
         * no account to open or find -- every account here has one.
         */
        if (! isset($claims->email) || ! is_string($claims->email) || $claims->email === '') {
            $this->refuse('no email');
        }

        if (! $this->isTrue($claims->email_verified ?? null)) {
            $this->refuse('email not verified');
        }

        return new AppleIdentityData(
            sub: $claims->sub,
            email: mb_strtolower(trim($claims->email)),
            clientId: (string) $claims->aud,
            isPrivateEmail: $this->isTrue($claims->is_private_email ?? null),
        );
    }

    /**
     * Apple has sent these flags both as JSON booleans and as the strings
     * `"true"`/`"false"`. Both spellings of true count; anything else is false.
     */
    private function isTrue(mixed $value): bool
    {
        return $value === true || $value === 'true';
    }

    /**
     * The reason goes to the log and nowhere else. The token itself is never
     * logged: until it expires it is as good as the account.
     */
    private function refuse(string $reason): never
    {
        Log::warning('apple id token refused', ['reason' => $reason]);

        throw new InvalidProviderTokenException(self::PROVIDER);
    }
}

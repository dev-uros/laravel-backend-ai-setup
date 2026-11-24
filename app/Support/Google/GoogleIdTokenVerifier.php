<?php

namespace App\Support\Google;

use App\Exceptions\InvalidProviderTokenException;
use App\Exceptions\ProviderSignInUnavailableException;
use App\Support\IdToken\IdTokenIssuer;
use App\Support\IdToken\IdTokenKeysUnavailableException;
use App\Support\IdToken\IdTokenRejectedException;
use App\Support\IdToken\JwksIdTokenDecoder;
use Illuminate\Support\Facades\Log;
use stdClass;

/**
 * Checks a Google Sign-In ID token, offline, against Google's published keys.
 *
 * The signature, the key cache and the standard claims are
 * `JwksIdTokenDecoder`'s, shared with Apple. What is Google's own is here:
 * `aud` must be one of `services.google_oauth.client_ids` (the app passes the
 * web client ID as `webClientId`, which is what lands in `aud` on both
 * platforms; the iOS and Android IDs are accepted too), an address Google has
 * verified, the names, and `hd`.
 *
 * **No nonce.** The native Google SDK does not reliably carry one through; the
 * token's short life and `aud` are the protection.
 */
class GoogleIdTokenVerifier implements GoogleIdTokenVerifierInterface
{
    public const PROVIDER = 'Google';

    private const KEYS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    private const CACHE_PREFIX = 'google-oauth';

    private const ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    public function __construct(
        private readonly JwksIdTokenDecoder $decoder,
    ) {}

    public function verify(string $idToken): GoogleIdentityData
    {
        $issuer = new IdTokenIssuer(
            name: 'google',
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

        return $this->identity($claims);
    }

    /**
     * @return list<string>
     */
    private function clientIds(): array
    {
        $clientIds = config('services.google_oauth.client_ids');

        if (! is_array($clientIds) || $clientIds === []) {
            Log::error('google oauth client ids are not configured');

            throw new ProviderSignInUnavailableException(self::PROVIDER);
        }

        return array_values($clientIds);
    }

    private function identity(stdClass $claims): GoogleIdentityData
    {
        if (! isset($claims->email) || ! is_string($claims->email) || $claims->email === '') {
            $this->refuse('no email');
        }

        /**
         * The whole reason no code is mailed: Google has confirmed the address
         * belongs to this person. An unverified one proves nothing.
         */
        if (($claims->email_verified ?? false) !== true) {
            $this->refuse('email not verified');
        }

        return new GoogleIdentityData(
            sub: $claims->sub,
            email: mb_strtolower(trim($claims->email)),
            givenName: $this->nameOf($claims->given_name ?? null),
            familyName: $this->nameOf($claims->family_name ?? null),
            hostedDomain: is_string($claims->hd ?? null) && $claims->hd !== '' ? mb_strtolower($claims->hd) : null,
        );
    }

    /** Cut to the column: a Google profile name has no length limit of its own. */
    private function nameOf(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? mb_substr(trim($value), 0, 255) : null;
    }

    /**
     * The reason goes to the log and nowhere else. The token itself is never
     * logged: until it expires it is as good as the account.
     */
    private function refuse(string $reason): never
    {
        Log::warning('google id token refused', ['reason' => $reason]);

        throw new InvalidProviderTokenException(self::PROVIDER);
    }
}

<?php

namespace App\Support\IdToken;

use DomainException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use stdClass;
use Throwable;
use UnexpectedValueException;

/**
 * Checks an OpenID Connect ID token, offline, against the keys its issuer
 * publishes -- the part Google and Apple share.
 *
 * **No client secret, and no call to the issuer per sign-in.** An ID token is a
 * JWT the issuer signed; checking it needs the public half of the key it was
 * signed with, which the issuer publishes and rotates. The keys are cached for
 * as long as the issuer's own `Cache-Control` says, so a sign-in normally
 * reaches off the machine for nothing.
 *
 * What makes a token ours: the issuer's signature (`RS256` only -- the key
 * carries its algorithm, so `none` or an `HS256` token signed with the public
 * key as a secret are refused by the library), the issuer as `iss`, **one of our
 * clients as `aud`**, a live `exp` and a `sub`. Without the `aud` check a token
 * the same person got in somebody else's application would open an account
 * here. What the other claims mean is the calling verifier's business.
 */
class JwksIdTokenDecoder
{
    /**
     * Set when the keys were fetched again because a token named a key the
     * cache did not have. Without it, a caller sending made-up `kid`s would
     * make every request a round trip to the issuer.
     */
    private const REFRESH_COOLDOWN_SECONDS = 60;

    /** Used when the issuer's answer carries no `max-age`. */
    private const DEFAULT_KEYS_TTL_SECONDS = 3600;

    private const ALGORITHM = 'RS256';

    /**
     * For the difference between the issuer's clock, which stamped `iat` and
     * `exp`, and ours, which reads them.
     */
    private const LEEWAY_SECONDS = 60;

    private const TIMEOUT_SECONDS = 5;

    private const CONNECT_TIMEOUT_SECONDS = 3;

    private const RETRY_DELAYS_MS = [100, 500];

    /**
     * The verified claims, with `iss`, `aud`, `exp` and `sub` already checked.
     *
     * @throws IdTokenRejectedException when the token cannot be trusted; the message is the reason
     * @throws IdTokenKeysUnavailableException when the issuer's keys cannot be fetched
     */
    public function decode(string $idToken, IdTokenIssuer $issuer): stdClass
    {
        $keyId = $this->keyIdOf($idToken);
        $keys = $this->keys($issuer);

        if (! $this->hasKey($keys, $keyId)
            && Cache::add($issuer->cachePrefix.':jwks-refreshed', true, self::REFRESH_COOLDOWN_SECONDS)) {
            /** The issuer has just rotated, or the token is forged. One fresh look tells them apart. */
            $keys = $this->fetchKeys($issuer);
        }

        $claims = $this->verifySignature($idToken, $keys);

        $this->checkStandardClaims($claims, $issuer);

        return $claims;
    }

    /**
     * The `kid` from the token's header, read before anything is verified --
     * only to decide whether the cached keys are worth trusting to have it.
     */
    private function keyIdOf(string $idToken): string
    {
        $segments = explode('.', $idToken);

        if (count($segments) !== 3) {
            throw new IdTokenRejectedException('malformed token');
        }

        try {
            $header = JWT::jsonDecode(JWT::urlsafeB64Decode($segments[0]));
        } catch (Throwable) {
            throw new IdTokenRejectedException('unreadable header');
        }

        if (! $header instanceof stdClass || ! isset($header->kid) || ! is_string($header->kid)) {
            throw new IdTokenRejectedException('no key id');
        }

        return $header->kid;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function keys(IdTokenIssuer $issuer): array
    {
        $keys = Cache::get($issuer->cachePrefix.':jwks');

        return is_array($keys) ? $keys : $this->fetchKeys($issuer);
    }

    /**
     * @param  array<int, array<string, mixed>>  $keys
     */
    private function hasKey(array $keys, string $keyId): bool
    {
        foreach ($keys as $key) {
            if (($key['kid'] ?? null) === $keyId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchKeys(IdTokenIssuer $issuer): array
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->retry(
                    self::RETRY_DELAYS_MS,
                    when: fn (Throwable $exception): bool => $exception instanceof ConnectionException
                        || ($exception instanceof RequestException && $exception->response->serverError()),
                    throw: false,
                )
                ->get($issuer->keysUrl);
        } catch (ConnectionException $exception) {
            Log::warning($issuer->name.' oauth keys unreachable', ['reason' => $exception->getMessage()]);

            throw new IdTokenKeysUnavailableException;
        }

        $keys = $response->json('keys');

        if (! $response->successful() || ! is_array($keys) || $keys === []) {
            Log::error($issuer->name.' oauth keys request failed', ['http_status' => $response->status()]);

            throw new IdTokenKeysUnavailableException;
        }

        Cache::put($issuer->cachePrefix.':jwks', $keys, $this->ttlOf($response->header('Cache-Control')));

        return $keys;
    }

    private function ttlOf(string $cacheControl): int
    {
        if (preg_match('/max-age=(\d+)/', $cacheControl, $matches) === 1 && (int) $matches[1] > 0) {
            return (int) $matches[1];
        }

        return self::DEFAULT_KEYS_TTL_SECONDS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $keys
     */
    private function verifySignature(string $idToken, array $keys): stdClass
    {
        /**
         * The library's leeway is a static, so it is set for this call and put
         * back -- whatever else decodes a JWT should not inherit ours by
         * accident.
         */
        $leeway = JWT::$leeway;
        JWT::$leeway = self::LEEWAY_SECONDS;

        try {
            return JWT::decode($idToken, JWK::parseKeySet(['keys' => $keys], self::ALGORITHM));
        } catch (UnexpectedValueException|DomainException|InvalidArgumentException $exception) {
            /** Signature, expiry, not-yet-valid, wrong algorithm and unknown key all land here. */
            throw new IdTokenRejectedException($exception->getMessage());
        } finally {
            JWT::$leeway = $leeway;
        }
    }

    private function checkStandardClaims(stdClass $claims, IdTokenIssuer $issuer): void
    {
        if (! in_array($claims->iss ?? null, $issuer->issuers, true)) {
            throw new IdTokenRejectedException('wrong issuer');
        }

        /**
         * A list, not one value: Google's web, iOS and Android clients each have
         * an ID, and Apple's `aud` is the bundle ID of whichever of the three
         * app variants signed in. A JWT may also carry `aud` as an array; then
         * one of its entries must be ours.
         */
        $audiences = is_array($claims->aud ?? null) ? $claims->aud : [$claims->aud ?? null];

        if (array_intersect($audiences, $issuer->audiences) === []) {
            throw new IdTokenRejectedException('wrong audience');
        }

        /** The library checks `exp` only when there is one. Both issuers always send it; a token without one is not theirs. */
        if (! isset($claims->exp)) {
            throw new IdTokenRejectedException('no expiry');
        }

        if (! isset($claims->sub) || ! is_string($claims->sub) || $claims->sub === '') {
            throw new IdTokenRejectedException('no subject');
        }
    }
}

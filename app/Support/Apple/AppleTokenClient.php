<?php

namespace App\Support\Apple;

use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The only class that talks to Apple's token endpoint.
 *
 * App Review asks that deleting an account revokes its Sign in with Apple
 * authorisation. Revoking takes a refresh token, and the only way to get one
 * is to trade the authorization code the phone receives beside its identity
 * token. Both calls authenticate with a **client secret**: a JWT this class
 * signs itself with the `.p8` key (ES256, `kid` the Key ID, `iss` the Team ID,
 * `sub` the bundle ID the code was issued to). Apple lets one live for six months; it is signed per
 * call instead, so there is nothing to renew and nothing to cache.
 *
 * No `redirect_uri`: the phone signs in natively, and Apple asks for one only
 * when the code came from a web flow.
 */
class AppleTokenClient implements AppleTokenClientInterface
{
    private const TOKEN_URL = 'https://appleid.apple.com/auth/token';

    private const REVOKE_URL = 'https://appleid.apple.com/auth/revoke';

    private const AUDIENCE = 'https://appleid.apple.com';

    /** Somebody is waiting on the exchange: it runs inside signing in. */
    private const TIMEOUT_SECONDS = 5;

    private const CONNECT_TIMEOUT_SECONDS = 3;

    /** Long enough for one call and its clock skew; Apple allows up to six months. */
    private const CLIENT_SECRET_TTL_SECONDS = 300;

    public function exchangeCode(string $authorizationCode, string $clientId): AppleRefreshTokenData
    {
        $response = $this->post(self::TOKEN_URL, $clientId, [
            'code' => $authorizationCode,
            'grant_type' => 'authorization_code',
        ]);

        $refreshToken = $response->json('refresh_token');
        $sub = $this->subjectOf($response->json('id_token'));

        if (! is_string($refreshToken) || $refreshToken === '' || $sub === null) {
            throw new AppleTokenRequestFailedException('apple token answer without a refresh token or subject');
        }

        return new AppleRefreshTokenData($refreshToken, $sub);
    }

    public function revoke(string $refreshToken, string $clientId): void
    {
        $this->post(self::REVOKE_URL, $clientId, [
            'token' => $refreshToken,
            'token_type_hint' => 'refresh_token',
        ]);
    }

    /**
     * Not retried here: a code is spent by the first call that reaches Apple,
     * so a second one can only be refused. The revocation job retries on its
     * own schedule instead.
     *
     * @param  array<string, string>  $form
     */
    private function post(string $url, string $clientId, array $form): Response
    {
        try {
            $response = Http::asForm()
                ->timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->post($url, [
                    'client_id' => $clientId,
                    'client_secret' => $this->clientSecret($clientId),
                    ...$form,
                ]);
        } catch (ConnectionException $exception) {
            throw new AppleTokenRequestFailedException('apple token endpoint unreachable: '.$exception->getMessage());
        }

        if (! $response->successful()) {
            /** Apple's `error` is a short code such as `invalid_grant`; the body holds no secret. */
            $error = $response->json('error');

            throw new AppleTokenRequestFailedException(sprintf(
                'apple token endpoint answered %d %s',
                $response->status(),
                is_string($error) ? $error : '',
            ));
        }

        return $response;
    }

    private function clientSecret(string $clientId): string
    {
        $key = base64_decode($this->config('private_key_base64'), true);

        if ($key === false || $key === '') {
            throw new AppleTokenRequestFailedException('apple private key is not valid base64');
        }

        $now = time();

        try {
            return JWT::encode([
                'iss' => $this->config('team_id'),
                'iat' => $now,
                'exp' => $now + self::CLIENT_SECRET_TTL_SECONDS,
                'aud' => self::AUDIENCE,
                'sub' => $clientId,
            ], $key, 'ES256', $this->config('key_id'));
        } catch (Throwable $exception) {
            throw new AppleTokenRequestFailedException('apple private key cannot sign: '.$exception->getMessage());
        }
    }

    /**
     * The `sub` of the identity token in Apple's answer, read without checking
     * its signature: it arrived from Apple over TLS in answer to our own call,
     * which is the trust the signature would otherwise provide.
     */
    private function subjectOf(mixed $idToken): ?string
    {
        if (! is_string($idToken) || substr_count($idToken, '.') !== 2) {
            return null;
        }

        try {
            $claims = JWT::jsonDecode(JWT::urlsafeB64Decode(explode('.', $idToken)[1]));
        } catch (Throwable) {
            return null;
        }

        return is_object($claims) && isset($claims->sub) && is_string($claims->sub) && $claims->sub !== ''
            ? $claims->sub
            : null;
    }

    private function config(string $key): string
    {
        $value = config('services.apple_oauth.'.$key);

        if (! is_string($value) || $value === '') {
            throw new AppleTokenRequestFailedException('apple oauth '.$key.' is not configured');
        }

        return $value;
    }
}

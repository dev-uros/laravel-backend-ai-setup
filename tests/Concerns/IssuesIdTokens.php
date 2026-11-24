<?php

namespace Tests\Concerns;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;

/**
 * Stands in for Google's and Apple's key servers: one RSA key pair per test
 * class, published as a JWKS through `Http::fake`, and ID tokens signed with
 * it. The real verifiers and the real decoder run against them -- only the
 * network is fake.
 */
trait IssuesIdTokens
{
    private static ?string $idTokenPrivateKey = null;

    /** @var array<string, string>|null */
    private static ?array $idTokenJwk = null;

    protected const ID_TOKEN_KID = 'test-key-1';

    protected function fakeIdTokenKeys(): void
    {
        $this->ensureIdTokenKey();

        $keys = ['keys' => [self::$idTokenJwk]];

        Http::preventStrayRequests();
        Http::fake([
            'www.googleapis.com/oauth2/v3/certs' => Http::response($keys, 200, ['Cache-Control' => 'public, max-age=3600']),
            'appleid.apple.com/auth/keys' => Http::response($keys, 200),
        ]);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    protected function signIdToken(array $claims, string $kid = self::ID_TOKEN_KID): string
    {
        $this->ensureIdTokenKey();

        return JWT::encode($claims, (string) self::$idTokenPrivateKey, 'RS256', $kid);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function googleIdToken(array $overrides = []): string
    {
        return $this->signIdToken([
            'iss' => 'https://accounts.google.com',
            'aud' => 'web-client.apps.googleusercontent.com',
            'sub' => '110000000000000000001',
            'email' => 'ana@gmail.com',
            'email_verified' => true,
            'given_name' => 'Ana',
            'family_name' => 'Anić',
            'iat' => time(),
            'exp' => time() + 3600,
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function appleIdToken(string $rawNonce, array $overrides = []): string
    {
        return $this->signIdToken([
            'iss' => 'https://appleid.apple.com',
            'aud' => 'com.example.customers',
            'sub' => '000123.abcdef0123456789.0123',
            'email' => 'ana@icloud.com',
            'email_verified' => 'true',
            'nonce' => hash('sha256', $rawNonce),
            'iat' => time(),
            'exp' => time() + 600,
            ...$overrides,
        ]);
    }

    private function ensureIdTokenKey(): void
    {
        if (self::$idTokenPrivateKey !== null) {
            return;
        }

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $private);
        $details = openssl_pkey_get_details($key);

        self::$idTokenPrivateKey = $private;
        self::$idTokenJwk = [
            'kty' => 'RSA',
            'alg' => 'RS256',
            'use' => 'sig',
            'kid' => self::ID_TOKEN_KID,
            'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
            'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
        ];
    }
}

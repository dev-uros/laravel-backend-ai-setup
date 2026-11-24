<?php

namespace App\Data\MobileAuth;

use App\Http\Requests\MobileAuth\SignInWithAppleRequest;

/**
 * `nonce` is the raw value the app generated; `authorizationCode` is traded
 * for the refresh token that lets a deleted account's Apple authorisation be
 * revoked.
 */
final readonly class SignInWithAppleData
{
    public function __construct(
        public string $idToken,
        public string $nonce,
        public ?string $authorizationCode,
        public SessionContextData $context,
    ) {}

    public static function fromRequest(SignInWithAppleRequest $request): self
    {
        return new self(
            idToken: (string) $request->validated('id_token'),
            nonce: (string) $request->validated('nonce'),
            authorizationCode: $request->validated('authorization_code'),
            context: SessionContextData::fromRequest($request),
        );
    }
}

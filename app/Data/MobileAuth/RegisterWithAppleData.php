<?php

namespace App\Data\MobileAuth;

use App\Http\Requests\MobileAuth\RegisterWithAppleRequest;

final readonly class RegisterWithAppleData
{
    public function __construct(
        public string $idToken,
        public string $nonce,
        public ?string $authorizationCode,
        public RegistrationProfileData $profile,
        public SessionContextData $context,
    ) {}

    public static function fromRequest(RegisterWithAppleRequest $request): self
    {
        return new self(
            idToken: (string) $request->validated('id_token'),
            nonce: (string) $request->validated('nonce'),
            authorizationCode: $request->validated('authorization_code'),
            profile: RegistrationProfileData::fromRequest($request),
            context: SessionContextData::fromRequest($request),
        );
    }
}

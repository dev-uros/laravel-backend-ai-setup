<?php

namespace App\Data\MobileAuth;

use App\Http\Requests\MobileAuth\RegisterWithGoogleRequest;

final readonly class RegisterWithGoogleData
{
    public function __construct(
        public string $idToken,
        public RegistrationProfileData $profile,
        public SessionContextData $context,
    ) {}

    public static function fromRequest(RegisterWithGoogleRequest $request): self
    {
        return new self(
            idToken: (string) $request->validated('id_token'),
            profile: RegistrationProfileData::fromRequest($request),
            context: SessionContextData::fromRequest($request),
        );
    }
}

<?php

namespace App\Data\MobileAuth;

use App\Http\Requests\MobileAuth\RegisterWithCodeRequest;

final readonly class RegisterWithCodeData
{
    public function __construct(
        public string $email,
        public string $code,
        public RegistrationProfileData $profile,
        public SessionContextData $context,
    ) {}

    public static function fromRequest(RegisterWithCodeRequest $request): self
    {
        return new self(
            email: (string) $request->validated('email'),
            code: (string) $request->validated('code'),
            profile: RegistrationProfileData::fromRequest($request),
            context: SessionContextData::fromRequest($request),
        );
    }
}

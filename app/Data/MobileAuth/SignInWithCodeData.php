<?php

namespace App\Data\MobileAuth;

use App\Http\Requests\MobileAuth\SignInWithCodeRequest;

final readonly class SignInWithCodeData
{
    public function __construct(
        public string $email,
        public string $code,
        public SessionContextData $context,
    ) {}

    public static function fromRequest(SignInWithCodeRequest $request): self
    {
        return new self(
            email: (string) $request->validated('email'),
            code: (string) $request->validated('code'),
            context: SessionContextData::fromRequest($request),
        );
    }
}

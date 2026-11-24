<?php

namespace App\Data\MobileAuth;

use App\Http\Requests\MobileAuth\SignInWithGoogleRequest;

final readonly class SignInWithGoogleData
{
    public function __construct(
        public string $idToken,
        public SessionContextData $context,
    ) {}

    public static function fromRequest(SignInWithGoogleRequest $request): self
    {
        return new self(
            idToken: (string) $request->validated('id_token'),
            context: SessionContextData::fromRequest($request),
        );
    }
}

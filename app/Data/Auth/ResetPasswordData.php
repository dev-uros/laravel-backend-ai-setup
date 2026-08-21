<?php

namespace App\Data\Auth;

use App\Http\Requests\Auth\ResetPasswordRequest;

final readonly class ResetPasswordData
{
    public function __construct(
        public string $email,
        public string $token,
        public string $password,
    ) {}

    public static function fromRequest(ResetPasswordRequest $request): self
    {
        return new self(
            email: $request->string('email')->toString(),
            token: $request->string('token')->toString(),
            password: $request->string('password')->toString(),
        );
    }
}

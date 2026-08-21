<?php

namespace App\Data\Auth;

use App\Http\Requests\Auth\LoginRequest;

final readonly class LoginData
{
    public function __construct(
        public string $email,
        public string $password,
    ) {}

    /**
     * Build the DTO from a validated request.
     */
    public static function fromRequest(LoginRequest $request): self
    {
        return new self(
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
        );
    }
}

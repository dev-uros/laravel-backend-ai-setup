<?php

namespace App\Data\Auth;

use App\Http\Requests\Auth\ActivateAccountRequest;

final readonly class ActivateAccountData
{
    public function __construct(
        public string $email,
        public string $token,
        public string $password,
    ) {}

    /**
     * Build the DTO from a validated request.
     */
    public static function fromRequest(ActivateAccountRequest $request): self
    {
        return new self(
            email: $request->string('email')->toString(),
            token: $request->string('token')->toString(),
            password: $request->string('password')->toString(),
        );
    }
}

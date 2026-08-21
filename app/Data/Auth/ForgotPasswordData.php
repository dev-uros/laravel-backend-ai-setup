<?php

namespace App\Data\Auth;

use App\Http\Requests\Auth\ForgotPasswordRequest;

final readonly class ForgotPasswordData
{
    public function __construct(public string $email) {}

    public static function fromRequest(ForgotPasswordRequest $request): self
    {
        return new self(email: $request->string('email')->toString());
    }
}

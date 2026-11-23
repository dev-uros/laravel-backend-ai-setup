<?php

namespace App\Data\Auth;

use App\Data\InternalUserDevice\RegisterInternalUserDeviceData;
use App\Http\Requests\Auth\LoginRequest;

final readonly class LoginData
{
    /**
     * `device` is null for the web app, and the phone the session is on for
     * the mobile one.
     */
    public function __construct(
        public string $email,
        public string $password,
        public ?RegisterInternalUserDeviceData $device = null,
    ) {}

    /**
     * Build the DTO from a validated request.
     */
    public static function fromRequest(LoginRequest $request): self
    {
        return new self(
            email: $request->string('email')->toString(),
            password: $request->string('password')->toString(),
            device: $request->has('device')
                ? RegisterInternalUserDeviceData::fromArray($request->validated('device'))
                : null,
        );
    }
}

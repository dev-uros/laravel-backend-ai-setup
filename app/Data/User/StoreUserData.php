<?php

namespace App\Data\User;

use App\Enums\UserRole;
use App\Http\Requests\User\StoreUserRequest;

final readonly class StoreUserData
{
    public function __construct(
        public string $name,
        public string $surname,
        public string $email,
        public UserRole $role = UserRole::User,
    ) {}

    /**
     * Build the DTO from a validated request.
     *
     * The plain constructor stays usable from a command or a job.
     */
    public static function fromRequest(StoreUserRequest $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            surname: $request->string('surname')->toString(),
            email: $request->string('email')->toString(),
            role: $request->enum('role', UserRole::class) ?? UserRole::User,
        );
    }
}

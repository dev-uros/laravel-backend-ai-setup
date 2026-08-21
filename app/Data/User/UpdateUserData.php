<?php

namespace App\Data\User;

use App\Http\Requests\User\UpdateUserRequest;

final readonly class UpdateUserData
{
    public function __construct(
        public string $name,
        public string $surname,
        public string $email,
    ) {}

    /**
     * Build the DTO from a validated request.
     *
     * The plain constructor stays usable from a command or a job.
     */
    public static function fromRequest(UpdateUserRequest $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            surname: $request->string('surname')->toString(),
            email: $request->string('email')->toString(),
        );
    }
}

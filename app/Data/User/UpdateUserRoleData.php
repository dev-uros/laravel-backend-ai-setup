<?php

namespace App\Data\User;

use App\Enums\UserRole;
use App\Http\Requests\User\UpdateUserRoleRequest;

final readonly class UpdateUserRoleData
{
    public function __construct(
        public UserRole $role,
    ) {}

    /**
     * Build the DTO from a validated request.
     *
     * The plain constructor stays usable from a command or a job.
     */
    public static function fromRequest(UpdateUserRoleRequest $request): self
    {
        return new self(
            role: $request->enum('role', UserRole::class),
        );
    }
}

<?php

namespace App\Data\Auth;

use App\Models\User;

/**
 * What a successful login produces: the user, and the bearer token the SPA will
 * send on every subsequent request.
 */
final readonly class AuthenticatedUserData
{
    public function __construct(
        public User $user,
        public string $token,
    ) {}
}

<?php

namespace App\Data\MobileAuth;

use App\Models\MobileUser;

/**
 * What every way into the external app ends with: the account and the plain
 * bearer token, which exists only in this answer.
 */
final readonly class AuthenticatedMobileUserData
{
    public function __construct(
        public MobileUser $user,
        public string $token,
    ) {}
}

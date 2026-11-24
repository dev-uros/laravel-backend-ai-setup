<?php

namespace App\Data\MobileUser;

use App\Enums\AppLocale;

/**
 * The row a new account starts as, once the address has been proved.
 */
final readonly class NewMobileUserData
{
    public function __construct(
        public string $email,
        public string $username,
        public AppLocale $locale,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public bool $displayPersonalName = false,
        public ?string $googleId = null,
        public ?string $appleId = null,
        public ?string $avatarPath = null,
        public bool $isDemo = false,
    ) {}
}

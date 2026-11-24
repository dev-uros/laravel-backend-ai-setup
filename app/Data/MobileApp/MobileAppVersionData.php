<?php

namespace App\Data\MobileApp;

/**
 * What one platform's installed apps must be at least, and where to get the
 * update. Both null when nothing is configured.
 */
final readonly class MobileAppVersionData
{
    public function __construct(
        public ?string $minimumVersion,
        public ?string $storeUrl,
    ) {}
}

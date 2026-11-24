<?php

namespace App\Services\MobileApp;

use App\Data\MobileApp\MobileAppVersionData;
use App\Enums\DevicePlatform;

class ShowMobileAppVersionService
{
    /**
     * The minimum version and store link for one platform, from
     * `config/mobile.php`. An empty value is no value: an `.env` line left as
     * `MOBILE_IOS_MINIMUM_VERSION=` must not read as a minimum of "".
     */
    public function handle(DevicePlatform $platform): MobileAppVersionData
    {
        return new MobileAppVersionData(
            minimumVersion: $this->filled(config("mobile.minimum_version.{$platform->value}")),
            storeUrl: $this->filled(config("mobile.store_url.{$platform->value}")),
        );
    }

    private function filled(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}

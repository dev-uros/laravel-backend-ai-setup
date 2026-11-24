<?php

namespace App\Services\MobileApp;

use App\Data\MobileApp\MobileAppVersionData;
use App\Enums\DevicePlatform;
use App\Enums\MobileApp;

class ShowMobileAppVersionService
{
    /**
     * The minimum version and store link for one app on one platform, from
     * `config/mobile.php`. An empty value is no value: an `.env` line left as
     * `MOBILE_IOS_MINIMUM_VERSION=` must not read as a minimum of "".
     */
    public function handle(MobileApp $app, DevicePlatform $platform): MobileAppVersionData
    {
        return new MobileAppVersionData(
            minimumVersion: $this->filled(config("mobile.apps.{$app->value}.minimum_version.{$platform->value}")),
            storeUrl: $this->filled(config("mobile.apps.{$app->value}.store_url.{$platform->value}")),
        );
    }

    private function filled(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}

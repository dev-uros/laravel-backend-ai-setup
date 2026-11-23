<?php

namespace App\Enums;

/**
 * Which mobile platform a device runs. The values are React Native's
 * `Platform.OS`, so the app sends what it already has.
 */
enum DevicePlatform: string
{
    case Ios = 'ios';
    case Android = 'android';
}

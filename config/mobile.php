<?php

/**
 * The internal mobile app. Read by `GET /api/mobile/app-version/{platform}`,
 * which the app asks on every launch and every return to the foreground.
 *
 * `minimum_version` is the oldest version still allowed to run, per platform:
 * an installed app older than it shows a full-screen "update required" with a
 * button to `store_url`, and nothing else. Empty means no minimum. Raise it
 * when an API change breaks older apps, or to retire a broken release -- set it
 * only after the new version is live in that platform's store, or the app
 * points people at an update they cannot get yet.
 *
 * `store_url` is the app's page in the store (App Store:
 * https://apps.apple.com/app/id<number>, Google Play:
 * https://play.google.com/store/apps/details?id=<package>). Without it the
 * screen tells people to ask their administrator instead of linking.
 */
return [

    'minimum_version' => [
        'ios' => env('MOBILE_IOS_MINIMUM_VERSION'),
        'android' => env('MOBILE_ANDROID_MINIMUM_VERSION'),
    ],

    'store_url' => [
        'ios' => env('MOBILE_IOS_STORE_URL'),
        'android' => env('MOBILE_ANDROID_STORE_URL'),
    ],

];

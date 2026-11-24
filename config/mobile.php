<?php

/**
 * The project's two mobile apps: `internal` (for the people who run the
 * system, `users`) and `external` (for customers, `mobile_users`).
 *
 * `GET /api/mobile/app-version/{app}/{platform}` reads `apps`, which each app
 * asks on every launch and every return to the foreground.
 *
 * `minimum_version` is the oldest version still allowed to run, per app and
 * platform: an installed app older than it shows a full-screen "update
 * required" with a button to `store_url`, and nothing else. Empty means no
 * minimum. Raise it when an API change breaks older apps, or to retire a
 * broken release -- only after the new version is live in that platform's
 * store, or the app points people at an update they cannot get yet.
 *
 * `store_url` is the app's page in the store (App Store:
 * https://apps.apple.com/app/id<number>, Google Play:
 * https://play.google.com/store/apps/details?id=<package>).
 */
return [

    'apps' => [
        'internal' => [
            'minimum_version' => [
                'ios' => env('MOBILE_INTERNAL_IOS_MINIMUM_VERSION'),
                'android' => env('MOBILE_INTERNAL_ANDROID_MINIMUM_VERSION'),
            ],
            'store_url' => [
                'ios' => env('MOBILE_INTERNAL_IOS_STORE_URL'),
                'android' => env('MOBILE_INTERNAL_ANDROID_STORE_URL'),
            ],
        ],
        'external' => [
            'minimum_version' => [
                'ios' => env('MOBILE_EXTERNAL_IOS_MINIMUM_VERSION'),
                'android' => env('MOBILE_EXTERNAL_ANDROID_MINIMUM_VERSION'),
            ],
            'store_url' => [
                'ios' => env('MOBILE_EXTERNAL_IOS_STORE_URL'),
                'android' => env('MOBILE_EXTERNAL_ANDROID_STORE_URL'),
            ],
        ],
    ],

    /**
     * The accounts App Store and Google Play reviewers sign in to the external
     * app with -- and Maestro, locally. `php artisan mobile:demo-accounts`
     * creates them from `emails` (comma-separated); each signs in with `code`
     * and is never mailed. A real account can never be issued `code`.
     * Without `code`, demo accounts are mailed a code like everybody else.
     */
    'demo' => [
        'emails' => array_values(array_filter(array_map(
            fn (string $email): string => mb_strtolower(trim($email)),
            explode(',', (string) env('MOBILE_DEMO_EMAILS', '')),
        ))),
        'code' => env('MOBILE_DEMO_CODE') ?: null,
    ],

];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /**
     * Optional. Needed only once "Enhanced security for push notifications"
     * is switched on for the project at expo.dev; without it Expo accepts
     * pushes from anyone holding a token.
     */
    'expo' => [
        'access_token' => env('EXPO_ACCESS_TOKEN'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /**
     * Sign in with Google in the external mobile app. Every client ID whose
     * tokens are accepted as `aud`, comma-separated: the web client (which the
     * app passes as `webClientId` -- what Google puts in `aud` on both
     * platforms), plus the iOS and Android clients. Empty: the route answers
     * 503 and the app hides its button anyway. See the app's docs/sign-in.md.
     */
    'google_oauth' => [
        'client_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('GOOGLE_OAUTH_CLIENT_IDS', ''))))),
    ],

    /**
     * Sign in with Apple in the external mobile app.
     *
     * `client_ids`: the bundle IDs of the app's variants, comma-separated --
     * the `aud` of an identity token. Enough to sign in.
     *
     * `team_id`, `key_id`, `private_key_base64`: the Sign in with Apple key
     * (.p8, base64 on one line), used only to revoke a deleted account's
     * authorisation, which App Review requires. Secrets: environment only,
     * never committed.
     */
    'apple_oauth' => [
        'client_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('APPLE_CLIENT_IDS', ''))))),
        'team_id' => env('APPLE_TEAM_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key_base64' => env('APPLE_PRIVATE_KEY_BASE64'),
    ],

];

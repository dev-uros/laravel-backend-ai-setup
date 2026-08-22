<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
     * Only the SPA, not `*`. The API is token-only and `supports_credentials`
     * is false, so a wildcard is not a session hijack -- but it does hand every
     * page on the internet a working client against this API from a visitor's
     * browser, and there is no reason to.
     *
     * Defaults to FRONTEND_URL, the same origin emailed links point at. Set
     * CORS_ALLOWED_ORIGINS to a comma-separated list when a second origin needs
     * in -- a staging build, or a preview deployment. An entry is an origin:
     * scheme, host and port, never a path, and a trailing slash never matches.
     */
    'allowed_origins' => array_values(array_filter(array_map(
        fn (string $origin): string => rtrim(trim($origin), '/'),
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://localhost:5173'))),
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    /*
     * The SPA reads this back so a user can quote it in a bug report, and so a
     * frontend error can be tagged with the id the backend logged under. A
     * cross-origin response hides every header that is not listed here.
     */
    'exposed_headers' => ['X-Request-Id'],

    'max_age' => 0,

    'supports_credentials' => false,

];

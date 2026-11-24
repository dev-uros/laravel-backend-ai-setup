<?php

/**
 * English field names for the external mobile app's API. The sentences
 * themselves are Laravel's own (this file is merged over the framework's
 * `en/validation.php`), so only `attributes` lives here -- the same keys as
 * `lang/sr/validation.php`.
 */

return [
    'custom' => [
        'username' => [
            'regex' => 'A username may contain only lower-case letters, digits, dots and underscores.',
        ],
    ],

    'attributes' => [
        'email' => 'email address',
        'code' => 'code',
        'username' => 'username',
        'first_name' => 'first name',
        'last_name' => 'last name',
        'display_personal_name' => 'name display',
        'avatar' => 'profile picture',
        'locale' => 'language',
        'id_token' => 'token',
        'nonce' => 'nonce',
        'authorization_code' => 'authorization code',
        'given_name' => 'first name',
        'family_name' => 'last name',
        'device' => 'device',
        'device.device_id' => 'device ID',
        'device.platform' => 'platform',
        'device_id' => 'device ID',
        'platform' => 'platform',
        'expo_push_notification_token' => 'notification token',
        'apple_authorization_code' => 'Apple authorization code',
    ],
];

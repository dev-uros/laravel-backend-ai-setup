<?php

/**
 * Everything the external mobile app's API says to a person, in English. The
 * same keys as `lang/sr/mobile.php`.
 */
return [
    'auth' => [
        'code_sent' => 'We sent a code to your email address.',
        'no_account' => 'There is no account with this email address.',
        'signed_in' => 'You are signed in.',
        'registered' => 'Your account has been created.',
        'signed_out' => 'You are signed out.',
        'invalid_code' => 'The code is wrong or has expired. Ask for a new one.',
        'resend_too_soon' => 'You can ask for a new code in :seconds s.',
        'account_not_found' => 'There is no account for this :provider account. Create one.',
        'account_exists' => 'An account with this email address already exists. Sign in instead.',
        'not_linkable' => 'This email address already has an account. Sign in with an email code.',
        'invalid_provider_token' => 'Signing in with :provider did not work. Please try again.',
        'provider_unavailable' => 'Signing in with :provider is not available right now.',
    ],
    'access' => [
        'mobile_only' => 'This part of the API is for the mobile app\'s users only.',
        'internal_only' => 'This part of the API is for staff only.',
        'blocked' => 'Your account has been blocked.',
    ],
    'account' => [
        'profile' => 'Account details.',
        'updated' => 'Your profile has been saved.',
        'avatar_updated' => 'Your profile picture has been saved.',
        'avatar_removed' => 'Your profile picture has been removed.',
        'deleted' => 'Your account has been deleted.',
    ],
    'device' => [
        'show' => 'Device details.',
        'saved' => 'Device saved.',
        'not_found' => 'This device is not registered.',
        'push_disabled' => 'Notifications are not switched on on this device.',
        'test_push_sent' => 'A test notification has been sent.',
    ],
    'mail' => [
        'code' => [
            'subject' => 'Your code: :code',
            'greeting' => 'Hello,',
            'line' => 'Enter this code in the :app app:',
            'expiry' => 'The code is valid for :minutes minutes.',
            'ignore' => 'If you did not ask for a code, you can ignore this message. Nobody can get into your account without it.',
        ],
    ],
    'push' => [
        'test' => [
            'title' => 'Test notification',
            'body' => 'Notifications work on this device.',
        ],
    ],
];

<?php

namespace App\Support\Auth;

/**
 * Makes the code the external app signs in and registers with.
 *
 * Six digits: a million combinations. Length alone is no defence against
 * unlimited guessing -- the attempt counter on `mobile_otp_codes` is the other
 * half. `random_int`, because this is a credential.
 */
class OtpGenerator
{
    public const LENGTH = 6;

    public function generate(): string
    {
        return str_pad((string) random_int(0, 10 ** self::LENGTH - 1), self::LENGTH, '0', STR_PAD_LEFT);
    }
}

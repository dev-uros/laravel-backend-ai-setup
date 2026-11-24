<?php

namespace App\Data\MobileAuth;

/**
 * What asking for a code answers: whether there was an account to send it to
 * (always true for registration, which refuses a taken address earlier), and
 * how long until another may be asked for -- the app counts down from it.
 */
final readonly class CodeRequestResultData
{
    public function __construct(
        public bool $accountExists,
        public int $retryAfter,
    ) {}
}

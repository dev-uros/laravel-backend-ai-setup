<?php

namespace App\Support\Google;

/**
 * Who Google says is holding the phone, read off a verified ID token.
 *
 * `sub` is the Google account's permanent identifier; the address on it can
 * change. `hostedDomain` is the `hd` claim, present only for a Google
 * Workspace account -- with a Gmail address, how signing in tells an address
 * Google is authoritative for from one it merely checked once.
 */
final readonly class GoogleIdentityData
{
    public function __construct(
        public string $sub,
        public string $email,
        public ?string $givenName = null,
        public ?string $familyName = null,
        public ?string $hostedDomain = null,
    ) {}
}

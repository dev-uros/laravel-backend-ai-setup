<?php

namespace App\Support\Apple;

/**
 * Who Apple says is holding the phone, read off a verified identity token.
 *
 * `sub` is the Apple ID's identifier for our team and does not change. **There
 * is no name**: Apple never puts one in the token and hands it to the phone
 * only the first time somebody authorises the app, so it arrives in the request
 * body instead. `clientId` is the `aud` the token was issued to -- the bundle ID
 * of the app variant -- which the token endpoint needs to be called with.
 */
final readonly class AppleIdentityData
{
    public function __construct(
        public string $sub,
        public string $email,
        public string $clientId,
        public bool $isPrivateEmail = false,
    ) {}
}

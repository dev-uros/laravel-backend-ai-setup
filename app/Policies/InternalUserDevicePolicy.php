<?php

namespace App\Policies;

use App\Models\User;

/**
 * Who may manage the phone their own session is on: everyone signed in, of
 * either role. The routes reach only the caller's own device -- they are
 * looked up by the session, never by an id -- so there is nothing to refuse
 * here. Stated anyway so the route file says who may call them.
 */
class InternalUserDevicePolicy
{
    public function manageOwn(User $user): bool
    {
        return true;
    }
}

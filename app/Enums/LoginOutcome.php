<?php

namespace App\Enums;

/**
 * How a login attempt ended.
 *
 * One column instead of a boolean plus a reason: there is exactly one outcome
 * per attempt, and a flag with a nullable explanation next to it always drifts
 * into states that cannot happen.
 */
enum LoginOutcome: string
{
    case Succeeded = 'succeeded';

    /**
     * No user with that address, or the wrong password. The two are deliberately
     * one outcome: recording them apart would put the very distinction the login
     * endpoint refuses to reveal into a table somebody will one day expose.
     */
    case InvalidCredentials = 'invalid_credentials';

    case AccountDeactivated = 'account_deactivated';
}

<?php

namespace App\Enums;

/**
 * What happened to a user account.
 *
 * The value is the sentence a person reads in the audit trail; `old_values` and
 * `new_values` on the row carry the proof. Both exist because a column diff on
 * its own is unreadable -- `deactivated_at: null -> 2026-08-20T20:25` says far
 * less than `user.deactivated` does.
 *
 * Add a case whenever a new use case touches a user, or its trail is silent.
 */
enum UserLogEvent: string
{
    case Created = 'user.created';
    case Updated = 'user.updated';
    case RoleChanged = 'user.role_changed';
    case Deactivated = 'user.deactivated';
    case Reactivated = 'user.reactivated';
    case ActivationLinkSent = 'user.activation_link_sent';
    case AvatarUpdated = 'user.avatar_updated';
    case AvatarRemoved = 'user.avatar_removed';
    case Activated = 'user.activated';
    case PasswordReset = 'user.password_reset';
}

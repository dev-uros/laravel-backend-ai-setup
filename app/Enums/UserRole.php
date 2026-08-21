<?php

namespace App\Enums;

/**
 * What a user is allowed to do, in the coarsest possible terms.
 *
 * Two roles on purpose: an administrator who may do everything the API offers,
 * and a plain user who may only read. Anything finer -- per-resource
 * permissions, ownership, teams -- is a different design, not another case
 * added here.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case User = 'user';
}

<?php

namespace App\Enums;

/**
 * Which of the project's two mobile apps is asking: the one for the people who
 * run the system (`users`), or the one for customers (`mobile_users`). Each has
 * its own minimum version, because each ships on its own schedule.
 */
enum MobileApp: string
{
    case Internal = 'internal';
    case External = 'external';
}

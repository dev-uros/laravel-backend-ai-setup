<?php

namespace App\Enums;

/**
 * The languages the external mobile app speaks, and its accounts' mail and
 * pushes are written in. Serbian (Latin script) is the default.
 */
enum AppLocale: string
{
    case Sr = 'sr';
    case En = 'en';

    public const DEFAULT = self::Sr;
}

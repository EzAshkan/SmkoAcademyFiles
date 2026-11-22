<?php

namespace App\Support;

/**
 * Public mode (SMKO_PUBLIC_MODE=true): a second deployment of the same site where anyone can
 * browse and download without an account. Only the admin panel needs a login. Student accounts,
 * folder access, Telegram delivery/alerts and the student import are switched off.
 */
class Mode
{
    public static function isPublic(): bool
    {
        return (bool) config('smko.public_mode');
    }

    /** Student accounts, access control and Telegram features. */
    public static function hasStudents(): bool
    {
        return ! static::isPublic();
    }
}

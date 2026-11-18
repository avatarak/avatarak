<?php

namespace App\Services;

use App\Models\User;

/** Placeholder until the notification mails are added; kept as a seam so callers never change. */
class SecurityNotices
{
    public static function send(User $user, string $kind, array $context = []): void {}
}

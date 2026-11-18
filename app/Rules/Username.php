<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Username implements ValidationRule
{
    public const RESERVED = [
        'admin', 'administrator', 'api', 'avatar', 'avatars', 'u', 'login', 'register', 'logout', 'account', 'accounts',
        'settings', 'support', 'help', 'about', 'www', 'root', 'null', 'undefined', 'verify-email', 'up', 'build', 'storage',
        'assets', 'static', 'public', 'favicon', 'robots', 'sitemap', 'dashboard', 'profile', 'profiles', 'user', 'users',
        'me', 'privacy', 'terms', 'thumb', 'developers', 'developer', 'pricing', 'blog', 'docs', 'status', 'health', 'security',
        'contact', 'home', 'index', 'app', 'auth', 'oauth', 'signup', 'signin', 'wordpress', 'gravatar', 'avatarak', 'iravatar', 'pricing', 'billing', 'domains', '_internal', 'site', 'webhooks', 'gallery', 'panel',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $v = (string) $value;
        if (! preg_match('/^[a-z][a-z0-9_]{2,31}$/', $v)) {
            $fail(__('app.username_format'));
        } elseif (in_array($v, self::RESERVED, true)) {
            $fail(__('app.username_reserved'));
        }
    }
}

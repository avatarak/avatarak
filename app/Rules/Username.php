<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Username implements ValidationRule
{
    public const RESERVED = ['admin', 'api', 'avatar', 'u', 'login', 'register', 'logout', 'account',
        'settings', 'support', 'help', 'about', 'www', 'root', 'null', 'verify-email', 'up'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $v = (string) $value;
        if (! preg_match('/^[a-z][a-z0-9_]{2,31}$/', $v)) {
            $fail('نام کاربری باید ۳ تا ۳۲ کاراکتر، با حرف انگلیسی کوچک شروع شود و فقط شامل حرف، عدد و _ باشد.');
        } elseif (in_array($v, self::RESERVED, true)) {
            $fail('این نام کاربری مجاز نیست.');
        }
    }
}

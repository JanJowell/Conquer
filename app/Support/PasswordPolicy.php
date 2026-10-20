<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class PasswordPolicy
{
    public const MIN_LENGTH = 12;

    public const DESCRIPTION = 'Use at least 12 characters with uppercase and lowercase letters, a number, and a symbol.';

    public static function rule(): Password
    {
        $rule = Password::min(self::MIN_LENGTH)
            ->mixedCase()
            ->letters()
            ->numbers()
            ->symbols();

        return app()->isProduction() ? $rule->uncompromised() : $rule;
    }
}

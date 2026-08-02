<?php

namespace App\Support;

use InvalidArgumentException;

class PhoneNumber
{
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        if (str_starts_with($digits, '0092')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '92'.substr($digits, 1);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '3')) {
            $digits = '92'.$digits;
        }

        if (! preg_match('/^923\d{9}$/', $digits)) {
            throw new InvalidArgumentException('Enter a valid Pakistani mobile number, for example +923001234567.');
        }

        return '+'.$digits;
    }
}

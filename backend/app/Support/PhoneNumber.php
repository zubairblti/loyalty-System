<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class PhoneNumber
{
    public static function normalize(string $phone): string
    {
        if (preg_match('/[^0-9+\-()\s]/', $phone)) {
            throw new InvalidArgumentException('Phone number may only contain digits and standard formatting characters.');
        }

        $digits = preg_replace('/\D/', '', $phone);
        if (str_starts_with($digits, '0092')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = '92'.substr($digits, 1);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '3')) {
            $digits = '92'.$digits;
        }

        if (! preg_match('/^923(?:[0-4]\d|55|70)\d{7}$/', $digits)) {
            throw new InvalidArgumentException('Enter a valid Pakistani mobile number, for example (0300)1234567.');
        }

        return '+'.$digits;
    }

    public static function display(?string $phone): ?string
    {
        if (! $phone) {
            return $phone;
        }

        $normalized = self::normalize($phone);
        $local = '0'.substr($normalized, 3);

        return '('.substr($local, 0, 4).')'.substr($local, 4);
    }

    public static function validated(string $phone, string $field = 'phone'): string
    {
        try {
            return self::normalize($phone);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([$field => $exception->getMessage()]);
        }
    }
}

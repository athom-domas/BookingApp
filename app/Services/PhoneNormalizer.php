<?php

namespace App\Services;

class PhoneNormalizer
{
    public static function normalize(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($digits, '0039')) {
            return '+39' . substr($digits, 4);
        }

        if (str_starts_with($digits, '39') && strlen($digits) >= 11) {
            return '+' . $digits;
        }

        if (str_starts_with($digits, '0')) {
            return '+39' . ltrim($digits, '0');
        }

        return '+39' . $digits;
    }
}

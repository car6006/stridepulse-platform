<?php

namespace App\Services;

class PhoneNumberNormalizer
{
    public function normalize(?string $phoneNumber): ?string
    {
        if ($phoneNumber === null) {
            return null;
        }

        $phoneNumber = trim($phoneNumber);

        if ($phoneNumber === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phoneNumber);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            if (strlen($digits) !== 10) {
                return null;
            }

            return '27'.substr($digits, 1);
        }

        if (str_starts_with($digits, '27')) {
            return strlen($digits) === 11 ? $digits : null;
        }

        $length = strlen($digits);

        if ($length < 8 || $length > 15) {
            return null;
        }

        return $digits;
    }
}

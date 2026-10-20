<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function decimals(string $currency): int
    {
        return match (strtoupper($currency)) {
            'JPY', 'KRW', 'VND', 'CLP', 'XOF', 'XAF' => 0,
            'BHD', 'KWD', 'OMR', 'JOD', 'TND' => 3,
            default => 2,
        };
    }

    public static function toMinor(string $amount, string $currency, bool $signed = false): int
    {
        $amount = trim($amount);
        $negative = $signed && str_starts_with($amount, '-');
        if ($negative) {
            $amount = substr($amount, 1);
        }
        $places = self::decimals($currency);
        $pattern = $places === 0 ? '/^\d{1,10}$/' : '/^\d{1,10}(?:\.\d{1,'.$places.'})?$/';
        if (! preg_match($pattern, $amount)) {
            throw new InvalidArgumentException('Enter a valid amount for '.$currency.'.');
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $factor = 10 ** $places;
        $minor = ((int) $whole * $factor) + (int) str_pad($fraction, $places, '0');
        if ($minor > 99999999999) {
            throw new InvalidArgumentException('Amount exceeds the permitted range.');
        }

        return $negative ? -$minor : $minor;
    }

    public static function format(int $minor, string $currency): string
    {
        $places = self::decimals($currency);
        $factor = 10 ** $places;
        $negative = $minor < 0 ? '-' : '';
        $minor = abs($minor);
        $whole = number_format(intdiv($minor, $factor), 0, '.', ',');
        $fraction = $places ? '.'.str_pad((string) ($minor % $factor), $places, '0', STR_PAD_LEFT) : '';

        return $negative.$whole.$fraction.' '.strtoupper($currency);
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

class Money
{
    /**
     * Format a decimal or numeric string/float to Bangladeshi Taka format: "৳ 1,250.00"
     */
    public static function format(string|int|float|null $amount): string
    {
        $numeric = (float) ($amount ?? 0);

        return '৳ '.number_format($numeric, 2, '.', ',');
    }

    /**
     * Format a quantity string/float: up to 3 decimal places without trailing zeros (e.g. "5" or "5.25" or "5.125")
     */
    public static function formatQty(string|int|float|null $qty): string
    {
        $numeric = (float) ($qty ?? 0);
        // format with 3 decimals and trim unnecessary trailing zeros
        $formatted = number_format($numeric, 3, '.', ',');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    /**
     * Safe BCMath addition
     */
    public static function add(string|int|float $a, string|int|float $b, int $scale = 2): string
    {
        return bcadd((string) $a, (string) $b, $scale);
    }

    /**
     * Safe BCMath subtraction
     */
    public static function sub(string|int|float $a, string|int|float $b, int $scale = 2): string
    {
        return bcsub((string) $a, (string) $b, $scale);
    }

    /**
     * Safe BCMath multiplication
     */
    public static function mul(string|int|float $a, string|int|float $b, int $scale = 2): string
    {
        return bcmul((string) $a, (string) $b, $scale);
    }

    /**
     * Safe BCMath division
     */
    public static function div(string|int|float $a, string|int|float $b, int $scale = 2): string
    {
        if ((float) $b === 0.0) {
            return '0.00';
        }

        return bcdiv((string) $a, (string) $b, $scale);
    }
}

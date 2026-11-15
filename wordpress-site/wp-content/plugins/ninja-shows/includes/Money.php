<?php
/**
 * Money helpers for the ninja-shows plugin. Prices are stored as whole cents.
 *
 * Kept free of WordPress functions so it can be unit tested without WordPress.
 *
 * @package NinjaShows
 */

namespace NinjaShows;

final class Money
{
    /** Currency code sent to the payment gateway. */
    public const CURRENCY = 'USD';

    /** Symbol printed before an amount. */
    public const SYMBOL = '$';

    /**
     * Parse a price typed by a person ("45", "45.5", "45.00", "1,250.99") into cents.
     *
     * Returns null for anything that is not a non-negative amount with at most two decimals.
     */
    public static function parseToCents(string $input): ?int
    {
        $value = str_replace(',', '', trim($input));
        if (!preg_match('/^(\d{1,7})(?:\.(\d{1,2}))?$/', $value, $parts)) {
            return null;
        }

        $whole = (int) $parts[1];
        $fraction = isset($parts[2]) ? (int) str_pad($parts[2], 2, '0') : 0;

        return $whole * 100 + $fraction;
    }

    /**
     * Format cents for display, e.g. 125099 becomes "$1,250.99".
     */
    public static function format(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);

        return sprintf('%s%s%s.%02d', $sign, self::SYMBOL, number_format(intdiv($abs, 100)), $abs % 100);
    }
}

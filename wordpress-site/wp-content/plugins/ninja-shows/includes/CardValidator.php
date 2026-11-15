<?php
/**
 * Card number, expiry and security code checks. These are format checks only:
 * they do not say whether a card can pay. That is the gateway's job.
 *
 * Kept free of WordPress functions so it can be unit tested without WordPress.
 *
 * @package NinjaShows
 */

namespace NinjaShows;

use DateTimeImmutable;

final class CardValidator
{
    /** Years ahead that an expiry date may be. */
    private const MAX_YEARS_AHEAD = 20;

    /** Removes spaces and dashes people type between groups of digits. */
    public static function normalizeNumber(string $input): string
    {
        return preg_replace('/[\s-]+/', '', trim($input)) ?? '';
    }

    /**
     * True when the number is 12 to 19 digits and passes the Luhn check.
     */
    public static function isValidNumber(string $number): bool
    {
        if (!preg_match('/^\d{12,19}$/', $number)) {
            return false;
        }

        return self::luhnValid($number);
    }

    /** Last four digits of a card number, for display. */
    public static function lastFour(string $number): string
    {
        return substr($number, -4) ?: '';
    }

    /**
     * True when the month is 1 to 12 and the card has not expired by the end of
     * that month. The year must be a four-digit year, at most 20 years ahead.
     */
    public static function isUnexpired(int $month, int $year, DateTimeImmutable $now): bool
    {
        if ($month < 1 || $month > 12) {
            return false;
        }

        $currentYear = (int) $now->format('Y');
        $currentMonth = (int) $now->format('n');
        if ($year < $currentYear || $year > $currentYear + self::MAX_YEARS_AHEAD) {
            return false;
        }

        return $year > $currentYear || $month >= $currentMonth;
    }

    /** True for a 3 or 4 digit security code. */
    public static function isValidCvc(string $cvc): bool
    {
        return (bool) preg_match('/^\d{3,4}$/', $cvc);
    }

    /** Luhn checksum over a string of digits. */
    private static function luhnValid(string $digits): bool
    {
        $sum = 0;
        $double = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $digit = (int) $digits[$i];
            if ($double) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
            $double = !$double;
        }

        return $sum % 10 === 0;
    }
}

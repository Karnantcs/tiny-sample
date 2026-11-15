<?php
/**
 * Parsing and formatting for the show fields (date and time, ticket count).
 *
 * Kept free of WordPress functions so it can be unit tested without WordPress.
 *
 * @package NinjaShows
 */

namespace NinjaShows;

use DateTimeImmutable;

final class ShowFields
{
    /** Format of the value a datetime-local input sends, e.g. 2025-06-01T19:30. */
    public const DATE_FORMAT = 'Y-m-d\TH:i';

    /** Most tickets a show can have in stock. */
    public const MAX_TICKETS = 999999;

    /**
     * Returns the date and time in DATE_FORMAT, or null when it is not a real date and time.
     */
    public static function parseDate(string $input): ?string
    {
        $input = trim($input);
        $date = DateTimeImmutable::createFromFormat('!' . self::DATE_FORMAT, $input);
        if ($date === false || $date->format(self::DATE_FORMAT) !== $input) {
            return null;
        }

        return $input;
    }

    /**
     * Returns the number of tickets (0 or more), or null when the input is not a whole number in range.
     */
    public static function parseTickets(string $input): ?int
    {
        $input = trim($input);
        if (!preg_match('/^\d{1,6}$/', $input)) {
            return null;
        }
        $tickets = (int) $input;

        return $tickets <= self::MAX_TICKETS ? $tickets : null;
    }

    /**
     * Shows a stored date as "Sat 1 Jun 2025, 7:30 pm". Returns '' when the date is empty or invalid.
     */
    public static function formatForDisplay(string $stored): string
    {
        $date = DateTimeImmutable::createFromFormat('!' . self::DATE_FORMAT, $stored);
        if ($date === false || $date->format(self::DATE_FORMAT) !== $stored) {
            return '';
        }

        return $date->format('D j M Y, g:i a');
    }
}

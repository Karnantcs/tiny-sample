<?php
/**
 * Parsing for the shoe fields (size and pairs in stock).
 *
 * Kept free of WordPress functions so it can be unit tested without WordPress.
 *
 * @package NinjaShows
 */

namespace NinjaShows;

final class ShoeFields
{
    /** Longest size label, such as "US 10.5". */
    public const MAX_SIZE_LENGTH = 20;

    /** Most pairs a shoe listing can have in stock. */
    public const MAX_STOCK = 999999;

    /** A size starts with a letter or digit, then letters, digits, spaces, dots, slashes or dashes. */
    private const SIZE_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9 .\/-]*$/';

    /**
     * Returns the trimmed size label, or null when it is empty, too long or has characters a size never has.
     */
    public static function parseSize(string $input): ?string
    {
        $input = trim($input);
        if ($input === '' || strlen($input) > self::MAX_SIZE_LENGTH || !preg_match(self::SIZE_PATTERN, $input)) {
            return null;
        }

        return $input;
    }

    /**
     * Returns the number of pairs in stock (0 or more), or null when the input is not a whole number in range.
     */
    public static function parseStock(string $input): ?int
    {
        $input = trim($input);
        if (!preg_match('/^\d{1,6}$/', $input)) {
            return null;
        }
        $stock = (int) $input;

        return $stock <= self::MAX_STOCK ? $stock : null;
    }
}

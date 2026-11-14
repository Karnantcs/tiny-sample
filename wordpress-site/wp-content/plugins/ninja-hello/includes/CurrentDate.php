<?php
/**
 * Date text for the [ninja_time] shortcode.
 *
 * Kept free of WordPress functions so it can be unit tested without WordPress.
 *
 * @package NinjaHello
 */

namespace NinjaHello;

use DateTimeInterface;

final class CurrentDate
{
    /** Output format, for example 2026-11-14. */
    public const FORMAT = 'Y-m-d';

    /**
     * Format a point in time as a plain-text date.
     *
     * The date is taken in the timezone the given DateTime is in. The result is
     * plain text: escape it for HTML before printing it.
     */
    public static function format(DateTimeInterface $now): string
    {
        return $now->format(self::FORMAT);
    }
}

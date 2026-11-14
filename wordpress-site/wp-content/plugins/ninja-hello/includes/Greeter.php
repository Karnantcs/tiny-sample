<?php
/**
 * Greeting logic for the ninja-hello plugin.
 *
 * Kept free of WordPress functions so it can be unit tested without WordPress.
 *
 * @package NinjaHello
 */

namespace NinjaHello;

final class Greeter
{
    /** Name used when none (or only whitespace) is given. */
    public const DEFAULT_NAME = 'world';

    /**
     * Build a plain-text greeting for the given name.
     *
     * Surrounding whitespace is trimmed. An empty or whitespace-only name
     * falls back to DEFAULT_NAME. The result is plain text: escape it for
     * HTML before printing it.
     */
    public static function greeting(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            $name = self::DEFAULT_NAME;
        }

        return 'Hello, ' . $name . '!';
    }
}

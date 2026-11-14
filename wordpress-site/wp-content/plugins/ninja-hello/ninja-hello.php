<?php
/**
 * Plugin Name: Ninja Hello
 * Description: Adds the [ninja_hello name="..."] shortcode, which prints a greeting, and [ninja_time], which prints the current date.
 * Version:     1.0.0
 * Requires PHP: 8.1
 * License:     GPL-2.0-or-later
 * Text Domain: ninja-hello
 *
 * @package NinjaHello
 */

if (!defined('ABSPATH')) {
    exit; // Not loaded by WordPress.
}

require_once __DIR__ . '/includes/Greeter.php';
require_once __DIR__ . '/includes/CurrentDate.php';

/**
 * Render the [ninja_hello] shortcode.
 *
 * @param array|string $atts Shortcode attributes.
 * @return string HTML-escaped greeting.
 */
function ninja_hello_shortcode($atts): string
{
    $atts = shortcode_atts(
        array(
            'name' => '',
        ),
        is_array($atts) ? $atts : array(),
        'ninja_hello'
    );

    return '<span class="ninja-hello">' . esc_html(\NinjaHello\Greeter::greeting((string) $atts['name'])) . '</span>';
}

add_shortcode('ninja_hello', 'ninja_hello_shortcode');

/**
 * Render the [ninja_time] shortcode.
 *
 * Prints today's date in the site's timezone (Settings > General).
 *
 * @param array|string $atts Shortcode attributes. None are used.
 * @return string HTML-escaped date.
 */
function ninja_time_shortcode($atts): string
{
    $now = new DateTimeImmutable('now', wp_timezone());

    return '<span class="ninja-time">' . esc_html(\NinjaHello\CurrentDate::format($now)) . '</span>';
}

add_shortcode('ninja_time', 'ninja_time_shortcode');

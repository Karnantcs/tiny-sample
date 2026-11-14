<?php
/**
 * Plugin Name: Ninja Hello
 * Description: Adds the [ninja_hello name="..."] shortcode, which prints a greeting, [ninja_time], which prints the current date, and [ninja_quote], which shows a random quote.
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

/** Random quote API used by [ninja_quote]. */
const NINJA_QUOTE_API_URL = 'https://api.quotable.io/random';

/**
 * Render the [ninja_quote] shortcode.
 *
 * The server does not fetch the quote. It prints a placeholder with the API address,
 * and assets/quote.js asks the API from the browser when the page loads.
 *
 * @param array|string $atts Shortcode attributes. None are used.
 * @return string HTML for the quote block.
 */
function ninja_quote_shortcode($atts): string
{
    wp_enqueue_script(
        'ninja-quote',
        plugins_url('assets/quote.js', __FILE__),
        array(),
        '1.0.0',
        true
    );

    return '<figure class="ninja-quote" data-api-url="' . esc_url(NINJA_QUOTE_API_URL) . '">'
        . '<blockquote class="ninja-quote-text">Loading a quote&hellip;</blockquote>'
        . '<figcaption class="ninja-quote-author"></figcaption>'
        . '</figure>';
}

add_shortcode('ninja_quote', 'ninja_quote_shortcode');

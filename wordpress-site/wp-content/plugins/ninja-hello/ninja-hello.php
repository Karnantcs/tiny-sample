<?php
/**
 * Plugin Name: Ninja Hello
 * Description: Adds the [ninja_hello name="..."] shortcode, which prints a greeting.
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

<?php
/**
 * Ninja Theme setup.
 *
 * @package NinjaTheme
 */

if (!defined('ABSPATH')) {
    exit; // Not loaded by WordPress.
}

function ninja_theme_setup(): void
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption'));
}
add_action('after_setup_theme', 'ninja_theme_setup');

// Loads style.css. The URL comes from the site address, so it follows the address the site is opened at.
function ninja_theme_styles(): void
{
    wp_enqueue_style('ninja-theme', get_stylesheet_uri(), array(), wp_get_theme()->get('Version'));
}
add_action('wp_enqueue_scripts', 'ninja_theme_styles');

// Behind the NinjaBots preview proxy, WordPress's canonical redirect would compare the browser's
// address with the local one and redirect there. The preview sets NINJA_PROXIED in its wp-config.php.
if (defined('NINJA_PROXIED') && NINJA_PROXIED) {
    add_filter('redirect_canonical', '__return_false');
}

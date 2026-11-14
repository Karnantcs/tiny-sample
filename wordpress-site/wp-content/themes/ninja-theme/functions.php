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

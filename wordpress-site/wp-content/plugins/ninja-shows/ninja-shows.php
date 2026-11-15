<?php
/**
 * Plugin Name: Ninja Shows
 * Description: Sells show tickets with a mock payment gateway. Adds the [ninja_shows] shortcode.
 * Version:     1.0.0
 * Requires PHP: 8.1
 * License:     GPL-2.0-or-later
 * Text Domain: ninja-shows
 *
 * @package NinjaShows
 */

if (!defined('ABSPATH')) {
    exit; // Not loaded by WordPress.
}

$ninja_shows_includes = __DIR__ . '/includes';
require_once $ninja_shows_includes . '/Money.php';
require_once $ninja_shows_includes . '/CardValidator.php';
require_once $ninja_shows_includes . '/ShowFields.php';
require_once $ninja_shows_includes . '/Checkout.php';
require_once $ninja_shows_includes . '/Payments/PaymentRequest.php';
require_once $ninja_shows_includes . '/Payments/PaymentResult.php';
require_once $ninja_shows_includes . '/Payments/PaymentGateway.php';
require_once $ninja_shows_includes . '/Payments/MockGateway.php';
require_once $ninja_shows_includes . '/PostTypes.php';
require_once $ninja_shows_includes . '/Setup.php';
require_once $ninja_shows_includes . '/Shop.php';

unset($ninja_shows_includes);

NinjaShows\PostTypes::register();
NinjaShows\Shop::register();
register_activation_hook(__FILE__, ['NinjaShows\\Setup', 'activate']);

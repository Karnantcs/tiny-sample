<?php
/**
 * Loads the ninja-shows logic classes for the tests. The plugin has no Composer autoload,
 * so the tests load the same files that ninja-shows.php loads.
 *
 * @package NinjaShows
 */

$ninjaShowsIncludes = dirname(__DIR__) . '/wp-content/plugins/ninja-shows/includes';
foreach (
    [
        'Money.php',
        'CardValidator.php',
        'ShoeFields.php',
        'Checkout.php',
        'Payments/PaymentRequest.php',
        'Payments/PaymentResult.php',
        'Payments/PaymentGateway.php',
        'Payments/MockGateway.php',
    ] as $file
) {
    require_once $ninjaShowsIncludes . '/' . $file;
}

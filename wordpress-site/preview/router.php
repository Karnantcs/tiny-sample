<?php
/**
 * Router for PHP's built-in web server (php -S), used by the NinjaBots preview.
 *
 * Existing files (CSS, images, wp-login.php, ...) are served as they are.
 * Everything else goes to WordPress, which handles pretty permalinks.
 *
 * @package NinjaHello
 */

$requested = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$target = realpath(__DIR__ . '/' . ltrim($requested, '/'));
$root = realpath(__DIR__);

// Only paths inside the web root are served directly.
if ($target !== false && $root !== false && str_starts_with($target, $root . DIRECTORY_SEPARATOR)) {
    if (is_file($target)) {
        return false;
    }
    if (is_dir($target) && is_file($target . '/index.php')) {
        return false;
    }
}

require __DIR__ . '/index.php';

<?php
/**
 * WordPress settings for the NinjaBots preview.
 *
 * The preview has no MySQL server, so the database is SQLite through the
 * wp-sqlite-db drop-in (wp-content/db.php). start.sh copies this file to the
 * web root on every start. The Docker setup keeps its own MySQL settings.
 *
 * @package NinjaHello
 */

// The SQLite drop-in ignores these, but WordPress expects them to be defined.
define('DB_NAME', 'wordpress');
define('DB_USER', 'wordpress');
define('DB_PASSWORD', '');
define('DB_HOST', 'localhost');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

$table_prefix = 'wp_';

// The preview can be opened on any host, so URLs follow the host of each request.
// Only letters, digits, dots, dashes, colons and brackets are accepted from the Host header.
$ninja_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$ninja_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
if (!preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $ninja_host)) {
    $ninja_host = 'localhost';
}
define('WP_HOME', $ninja_scheme . '://' . $ninja_host);
define('WP_SITEURL', WP_HOME);

define('WP_DEBUG', false);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once ABSPATH . 'wp-settings.php';

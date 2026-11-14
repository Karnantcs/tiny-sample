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

// The preview can be opened on any host and under a path of its own, so URLs follow
// the address the browser used. The preview proxy sends that address in the
// X-Forwarded-Proto, X-Forwarded-Host and X-Forwarded-Prefix headers. Without them
// (docker compose, the installer, tests) the Host header and the HTTPS setting are used.
// Each value is checked before use, and a value that fails the check is replaced by its default.

/**
 * Returns the first comma-separated value of a header, trimmed, or '' if the header is missing.
 */
function ninja_forwarded_value(string $name): string
{
    if (empty($_SERVER[$name])) {
        return '';
    }
    $parts = explode(',', (string) $_SERVER[$name], 2);

    return trim($parts[0]);
}

$ninja_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$ninja_proto = strtolower(ninja_forwarded_value('HTTP_X_FORWARDED_PROTO'));
if ($ninja_proto === 'http' || $ninja_proto === 'https') {
    $ninja_scheme = $ninja_proto;
}

// Only letters, digits, dots, dashes, colons and brackets are accepted as a host.
$ninja_host = ninja_forwarded_value('HTTP_X_FORWARDED_HOST');
if ($ninja_host === '') {
    $ninja_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
}
if (!preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $ninja_host)) {
    $ninja_host = 'localhost';
}

// The path prefix, e.g. /preview. Only plain path segments are accepted; the trailing slash is dropped.
$ninja_prefix = rtrim(ninja_forwarded_value('HTTP_X_FORWARDED_PREFIX'), '/');
if (!preg_match('#^(/[A-Za-z0-9._~\-]+)*$#', $ninja_prefix)) {
    $ninja_prefix = '';
}

// WordPress decides between http and https with is_ssl(), which reads $_SERVER['HTTPS'].
// Set it to match the scheme used for the URLs, so stylesheets and scripts use the same scheme as the links.
$_SERVER['HTTPS'] = $ninja_scheme === 'https' ? 'on' : 'off';

define('WP_HOME', $ninja_scheme . '://' . $ninja_host . $ninja_prefix);
define('WP_SITEURL', WP_HOME);

// True when a request came through the preview proxy. The theme then turns off WordPress's
// canonical redirect, which compares the request with the local address and would send the browser there.
define(
    'NINJA_PROXIED',
    ninja_forwarded_value('HTTP_X_FORWARDED_HOST') !== ''
        || ninja_forwarded_value('HTTP_X_FORWARDED_PROTO') !== ''
        || ninja_forwarded_value('HTTP_X_FORWARDED_PREFIX') !== ''
);

define('WP_DEBUG', false);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once ABSPATH . 'wp-settings.php';

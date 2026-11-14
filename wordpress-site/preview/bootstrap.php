<?php
/**
 * Installs WordPress for the NinjaBots preview, once, with no install wizard.
 *
 * Run by start.sh before the server starts: php bootstrap.php <web root>
 *
 * It does what docker/ninja-bootstrap.sh does for the Docker setup: installs
 * WordPress with the admin account, activates ninja-theme and ninja-hello,
 * and creates a Home page with [ninja_hello name="Ada"] and [ninja_time] as the front page.
 * If WordPress is already installed, nothing is changed.
 *
 * Settings come from the environment: WP_TITLE, WP_ADMIN_USER, WP_ADMIN_EMAIL
 * and WP_ADMIN_PASSWORD. If no password is set, a random one is generated and
 * printed once.
 *
 * @package NinjaHello
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$root = $argv[1] ?? '';
if ($root === '' || !is_file($root . '/wp-load.php')) {
    fwrite(STDERR, "preview: WordPress was not found in '$root'\n");
    exit(1);
}

// wp-config.php builds WP_HOME from the host, so give the install the same host the server will use.
$port = getenv('PORT') ?: '8080';
$_SERVER['HTTP_HOST'] = 'localhost:' . $port;

// Without this, WordPress stops with a silent redirect to the install wizard when the site is not installed yet.
define('WP_INSTALLING', true);

require $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';

if (is_blog_installed()) {
    echo "preview: WordPress is already installed\n";
    exit(0);
}

$title = getenv('WP_TITLE') ?: 'Ninja Site';
$user = getenv('WP_ADMIN_USER') ?: 'admin';
$email = getenv('WP_ADMIN_EMAIL') ?: 'admin@example.com';
$password = getenv('WP_ADMIN_PASSWORD') ?: '';
$generated = $password === '';
if ($generated) {
    $password = wp_generate_password(20, true, false);
}

echo "preview: installing WordPress\n";
wp_install($title, $user, $email, true, '', $password);

switch_theme('ninja-theme');
$plugin = activate_plugin('ninja-hello/ninja-hello.php');
if (is_wp_error($plugin)) {
    fwrite(STDERR, 'preview: could not activate ninja-hello: ' . $plugin->get_error_message() . "\n");
    exit(1);
}

$home_id = wp_insert_post(
    array(
        'post_type' => 'page',
        'post_title' => 'Home',
        'post_status' => 'publish',
        'post_author' => 1,
        'post_content' => '[ninja_hello name="Ada"]' . "\n\n" . '[ninja_time]',
    ),
    true
);
if (is_wp_error($home_id)) {
    fwrite(STDERR, 'preview: could not create the Home page: ' . $home_id->get_error_message() . "\n");
    exit(1);
}

update_option('show_on_front', 'page');
update_option('page_on_front', $home_id);

echo "preview: WordPress is ready (Home page id $home_id)\n";
if ($generated) {
    echo "preview: admin login is '$user' with the generated password: $password\n";
    echo "preview: set WP_ADMIN_PASSWORD to choose your own before the first start\n";
} else {
    echo "preview: admin login is '$user' (password from WP_ADMIN_PASSWORD)\n";
}

#!/bin/sh
# Preconfigures WordPress on first start, so there is no installer to click through.
#
# It runs in the background while the official WordPress entrypoint writes
# wp-config.php and starts Apache. Once the database is reachable it:
#   1. installs WordPress with the admin account from the environment,
#   2. activates the Ninja Theme and the Ninja Hello plugin,
#   3. creates a Home page with [ninja_hello] and [ninja_time] and makes it the front page.
# On later starts WordPress is already installed, so nothing is changed.
set -u

WEB_ROOT=/var/www/html
WP="wp --path=$WEB_ROOT --allow-root"

(
    # Wait for the entrypoint to copy the files and write wp-config.php.
    tries=0
    until [ -f "$WEB_ROOT/wp-config.php" ] \
        && [ -d "$WEB_ROOT/wp-content/themes/ninja-theme" ] \
        && [ -d "$WEB_ROOT/wp-content/plugins/ninja-hello" ]; do
        tries=$((tries + 1))
        if [ "$tries" -gt 300 ]; then
            echo "ninja-bootstrap: gave up waiting for WordPress files" >&2
            exit 1
        fi
        sleep 2
    done

    # Wait for the database (MySQL can take a while to accept connections).
    tries=0
    until $WP db check >/dev/null 2>&1; do
        tries=$((tries + 1))
        if [ "$tries" -gt 150 ]; then
            echo "ninja-bootstrap: database is not reachable, WordPress was not set up" >&2
            exit 1
        fi
        sleep 2
    done

    if $WP core is-installed >/dev/null 2>&1; then
        echo "ninja-bootstrap: WordPress is already installed"
        exit 0
    fi

    if [ -z "${WP_ADMIN_PASSWORD:-}" ]; then
        echo "ninja-bootstrap: WP_ADMIN_PASSWORD is not set, WordPress was not set up" >&2
        exit 1
    fi

    echo "ninja-bootstrap: installing WordPress"
    $WP core install \
        --url="${WP_URL:-http://localhost:8080}" \
        --title="${WP_TITLE:-Ninja Site}" \
        --admin_user="${WP_ADMIN_USER:-admin}" \
        --admin_password="$WP_ADMIN_PASSWORD" \
        --admin_email="${WP_ADMIN_EMAIL:-admin@example.com}" \
        --skip-email || exit 1

    $WP theme activate ninja-theme || exit 1
    $WP plugin activate ninja-hello || exit 1

    home_id=$($WP post create --post_type=page --post_title=Home --post_status=publish \
        --post_content='[ninja_hello name="Ada"]

[ninja_time]' --porcelain) || exit 1
    $WP option update show_on_front page || exit 1
    $WP option update page_on_front "$home_id" || exit 1

    echo "ninja-bootstrap: WordPress is ready (Home page id $home_id)"
) &

# Hand over to the official entrypoint, which starts Apache (the CMD).
exec docker-entrypoint.sh "$@"

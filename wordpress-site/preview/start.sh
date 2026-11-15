#!/bin/sh
# Starts the WordPress preview for NinjaBots, with no MySQL and no install wizard.
#
# Run from wordpress-site/ (see "start" in ninjabots.json). It listens on $PORT.
#
# The web root is $RUN (default wordpress-site/.preview, git-ignored). It holds
# WordPress core from vendor/, the ninja-theme and ninja-hello from this repo,
# and the SQLite database. The database and uploads stay between starts, so the
# site is installed only the first time. Core is copied again only when its
# version changes; the theme, plugin and config are refreshed on every start.
#
# Optional settings: PHP_BIN (default php), NINJA_PREVIEW_DIR (web root path),
# WP_ADMIN_PASSWORD, WP_ADMIN_USER, WP_ADMIN_EMAIL, WP_TITLE.
set -eu

HERE=$(cd "$(dirname "$0")" && pwd)
SITE=$(cd "$HERE/.." && pwd)
RUN="${NINJA_PREVIEW_DIR:-$SITE/.preview}"
PHP="${PHP_BIN:-php}"
PORT="${PORT:-8080}"

CORE=""
for dir in "$SITE/vendor/johnpbloch/wordpress-core" "$SITE/wordpress"; do
    if [ -f "$dir/wp-includes/version.php" ]; then
        CORE="$dir"
        break
    fi
done
if [ -z "$CORE" ]; then
    echo "preview: WordPress core was not found. Run 'composer install' in wordpress-site first." >&2
    exit 1
fi

# The SQLite drop-in, installed by Composer into vendor/ (see installer-paths in composer.json).
DROPIN="$SITE/vendor/aaemnnosttv/wp-sqlite-db/src/db.php"
if [ ! -f "$DROPIN" ]; then
    echo "preview: the wp-sqlite-db drop-in was not found. Run 'composer install' in wordpress-site first." >&2
    exit 1
fi

mkdir -p "$RUN"
if ! cmp -s "$CORE/wp-includes/version.php" "$RUN/wp-includes/version.php" 2>/dev/null; then
    echo "preview: copying WordPress core to $RUN"
    cp -R "$CORE/." "$RUN/"
fi

cp "$HERE/wp-config.php" "$HERE/router.php" "$RUN/"
cp "$DROPIN" "$RUN/wp-content/db.php"
mkdir -p "$RUN/wp-content/themes" "$RUN/wp-content/plugins"
rm -rf "$RUN/wp-content/themes/ninja-theme" "$RUN/wp-content/plugins/ninja-hello" "$RUN/wp-content/plugins/ninja-shows"
cp -R "$SITE/wp-content/themes/ninja-theme" "$RUN/wp-content/themes/"
cp -R "$SITE/wp-content/plugins/ninja-hello" "$RUN/wp-content/plugins/"
cp -R "$SITE/wp-content/plugins/ninja-shows" "$RUN/wp-content/plugins/"

"$PHP" -d memory_limit=256M "$HERE/bootstrap.php" "$RUN"

echo "preview: serving WordPress on port $PORT"
PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"
export PHP_CLI_SERVER_WORKERS
exec "$PHP" -S "0.0.0.0:$PORT" -t "$RUN" "$RUN/router.php"

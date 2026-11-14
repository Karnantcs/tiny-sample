# Official WordPress image with the Ninja theme and the ninja-hello plugin added.
# The official entrypoint copies /usr/src/wordpress into the web root on first start,
# so files placed under /usr/src/wordpress/wp-content are served.
FROM wordpress:php8.3-apache

COPY wp-content/themes/ninja-theme /usr/src/wordpress/wp-content/themes/ninja-theme
COPY wp-content/plugins/ninja-hello /usr/src/wordpress/wp-content/plugins/ninja-hello

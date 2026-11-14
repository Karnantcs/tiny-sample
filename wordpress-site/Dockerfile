# Official WordPress image with the Ninja theme and the ninja-hello plugin added.
# The official entrypoint copies /usr/src/wordpress into the web root on first start,
# so files placed under /usr/src/wordpress/wp-content are served.
FROM wordpress:php8.3-apache

# WP-CLI, used by the bootstrap script to install and configure WordPress.
ADD https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar /usr/local/bin/wp
RUN chmod +x /usr/local/bin/wp

COPY wp-content/themes/ninja-theme /usr/src/wordpress/wp-content/themes/ninja-theme
COPY wp-content/plugins/ninja-hello /usr/src/wordpress/wp-content/plugins/ninja-hello

# Installs and configures WordPress on first start, then runs the official entrypoint.
COPY docker/ninja-bootstrap.sh /usr/local/bin/ninja-bootstrap.sh
RUN chmod +x /usr/local/bin/ninja-bootstrap.sh

ENTRYPOINT ["/usr/local/bin/ninja-bootstrap.sh"]
CMD ["apache2-foreground"]

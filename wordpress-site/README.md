# Ninja WordPress site

A WordPress site in Docker with:

- **`ninja-theme`**: a minimal custom theme (`wp-content/themes/ninja-theme`).
- **`ninja-hello`**: a plugin with the shortcode `[ninja_hello name="..."]`, which prints `Hello, <name>!`. With no name, or a blank one, it prints `Hello, world!`.
- **PHPUnit tests** for the greeting logic. They run without a WordPress install.

## Layout

```
wordpress-site/
├── Dockerfile              # official wordpress image + theme + plugin
├── docker-compose.yml      # WordPress + MySQL 8.0
├── .env.example            # settings template (copy to .env)
├── composer.json           # PHPUnit and autoloading
├── phpunit.xml.dist
├── tests/GreeterTest.php
└── wp-content/
    ├── plugins/ninja-hello/
    │   ├── ninja-hello.php           # plugin file, registers the shortcode
    │   └── includes/Greeter.php      # greeting logic (no WordPress calls)
    └── themes/ninja-theme/           # style.css, index.php, functions.php
```

## Run the site with Docker

Requirements: Docker with Compose.

1. Create your settings file and replace the placeholder passwords:

   ```sh
   cp .env.example .env
   ```

   Edit `.env` and set `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD` and `WP_ADMIN_PASSWORD` to your own values.

2. Build and start the containers:

   ```sh
   docker compose up --build
   ```

3. Open http://localhost:8080 (or the `WP_PORT` you set). WordPress is already installed, so there is no installer. Log in at `/wp-admin` with `WP_ADMIN_USER` and `WP_ADMIN_PASSWORD` from `.env`.

   On first start, `docker/ninja-bootstrap.sh` installs WordPress, activates **Ninja Theme** and **Ninja Hello**, and creates a **Home** page that contains `[ninja_hello name="Ada"]` as the front page. The home page should show `Hello, Ada!`.

   The bootstrap only runs while WordPress is not yet installed. To start again from scratch, run `docker compose down -v` and then `docker compose up --build`.

To stop the site, run `docker compose down`. To also delete the database and uploads, run `docker compose down -v`.

If you already had a `.env` from before, add the `WP_ADMIN_PASSWORD` line from `.env.example`. Compose won't start without it.

## Run the tests

The tests need PHP 8.1+ and Composer. They don't need a WordPress install or a database.

```sh
composer install
composer test
```

`composer test` runs `vendor/bin/phpunit`.

## Notes

- No real passwords are stored in the repository. Keep your values in `.env`, which is git-ignored.
- The greeting is plain text. The shortcode escapes it with `esc_html()` before output.

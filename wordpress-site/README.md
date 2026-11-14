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

   Edit `.env` and set `MYSQL_PASSWORD` and `MYSQL_ROOT_PASSWORD` to your own values.

2. Build and start the containers:

   ```sh
   docker compose up --build
   ```

3. Open http://localhost:8080 (or the `WP_PORT` you set) and finish the WordPress installer.

4. In the admin, go to **Appearance → Themes** and activate **Ninja Theme**. Go to **Plugins** and activate **Ninja Hello**.

5. Put this in a page or post to test the shortcode:

   ```
   [ninja_hello name="Ada"]
   ```

To stop the site, run `docker compose down`. To also delete the database and uploads, run `docker compose down -v`.

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

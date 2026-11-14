# Ninja WordPress site

A WordPress site in Docker with:

- **`ninja-theme`**: a minimal custom theme (`wp-content/themes/ninja-theme`).
- **`ninja-hello`**: a plugin with the shortcode `[ninja_hello name="..."]`, which prints `Hello, <name>!`. With no name, or a blank one, it prints `Hello, world!`.
- **PHPUnit tests** for the greeting logic. They run without a WordPress install.

## Layout

```
wordpress-site/
├── Dockerfile              # official wordpress image + theme + plugin
├── docker-compose.yml      # WordPress + MySQL 8.0 (for real use)
├── .env.example            # settings template (copy to .env)
├── composer.json           # PHPUnit and autoloading
├── phpunit.xml.dist
├── preview/                # NinjaBots preview: WordPress on SQLite, no MySQL
│   ├── start.sh            # builds the web root in .preview/ and serves it on $PORT
│   ├── bootstrap.php       # installs WordPress once, with no wizard
│   ├── wp-config.php       # SQLite settings used by the preview
│   └── router.php          # routes pretty permalinks for php -S
├── tests/GreeterTest.php
├── tests/PreviewSiteTest.php
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

## Preview without Docker (NinjaBots)

The preview runs WordPress with PHP's built-in server and an SQLite database, so it needs no MySQL and no Docker. NinjaBots starts it with the `start` command in `ninjabots.json`, which runs `preview/start.sh`.

- On the first start, `preview/bootstrap.php` installs WordPress with no wizard, activates **Ninja Theme** and **Ninja Hello**, and creates a **Home** page with `[ninja_hello name="Ada"]` as the front page. The home page shows `Hello, Ada!`.
- The admin login is `WP_ADMIN_USER` (default `admin`). Set `WP_ADMIN_PASSWORD` to choose the password. If it is not set, a random password is generated and printed in the start log once.
- The database and uploads are kept in `wordpress-site/.preview/` (git-ignored) between starts. Delete that folder to start from scratch.
- To try it on your machine: `cd wordpress-site && PORT=8080 sh preview/start.sh`, then open http://localhost:8080.

The Docker setup above is unchanged and is still the way to run the site for real use.

## Run the tests

The tests need PHP 8.1+ with the `pdo_sqlite` extension, and Composer. They don't need a MySQL server.

```sh
composer install
composer test
```

`composer test` runs `vendor/bin/phpunit`. `GreeterTest` checks the greeting logic alone. `PreviewSiteTest` starts the preview on a free port with `preview/start.sh`, checks the home page, and checks that a second start doesn't reinstall. It is skipped if `pdo_sqlite` or `proc_open` is missing.

## Notes

- No real passwords are stored in the repository. Keep your values in `.env`, which is git-ignored.
- The greeting is plain text. The shortcode escapes it with `esc_html()` before output.

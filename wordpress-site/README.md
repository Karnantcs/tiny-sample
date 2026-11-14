# Ninja WordPress site

A WordPress site in Docker with:

- **`ninja-theme`**: a minimal custom theme (`wp-content/themes/ninja-theme`).
- **`ninja-hello`**: a plugin with the shortcode `[ninja_hello name="..."]`, which prints `Hello, <name>!`. With no name, or a blank one, it prints `Hello, world!`. It also has `[ninja_time]`, which prints today's date (for example `2026-11-14`) in the site's timezone.
- **`[ninja_quote]`**, a block that shows a random quote from [api.quotable.io](https://api.quotable.io). The browser fetches it when the page loads, and the **Random Quote** page (`/random-quote/`) holds it. If the API can't be reached, the block says the quote could not be loaded.
- **PHPUnit tests** for the greeting and date logic. They run without a WordPress install.

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
├── tests/CurrentDateTest.php
├── tests/PreviewSiteTest.php
└── wp-content/
    ├── plugins/ninja-hello/
    │   ├── ninja-hello.php           # plugin file, registers the shortcodes
    │   ├── assets/quote.js           # browser script that fetches the random quote
    │   └── includes/
    │       ├── Greeter.php           # greeting logic (no WordPress calls)
    │       └── CurrentDate.php       # date text for [ninja_time] (no WordPress calls)
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

   On first start, `docker/ninja-bootstrap.sh` installs WordPress, activates **Ninja Theme** and **Ninja Hello**, and creates a **Home** page that contains `[ninja_hello name="Ada"]` and `[ninja_time]` as the front page. The home page should show `Hello, Ada!` and then today's date.

   The bootstrap only runs while WordPress is not yet installed. To start again from scratch, run `docker compose down -v` and then `docker compose up --build`.

To stop the site, run `docker compose down`. To also delete the database and uploads, run `docker compose down -v`.

If you already had a `.env` from before, add the `WP_ADMIN_PASSWORD` line from `.env.example`. Compose won't start without it.

## Preview without Docker (NinjaBots)

The preview runs WordPress with PHP's built-in server and an SQLite database, so it needs no MySQL and no Docker. NinjaBots starts it with the `start` command in `ninjabots.json`, which runs `preview/start.sh`.

- On the first start, `preview/bootstrap.php` installs WordPress with no wizard, activates **Ninja Theme** and **Ninja Hello**, and creates a **Home** page with `[ninja_hello name="Ada"]` and `[ninja_time]` as the front page. The home page shows `Hello, Ada!` and then today's date.
- Every start also makes sure a **Random Quote** page exists at `/random-quote/` (with `[ninja_quote]`) and that pretty permalinks are on. An older database gets the page on its next start. Existing content is not changed.
- The database is kept between starts, so a site installed before the date was added keeps its old Home page. To get the date on it, delete `wordpress-site/.preview/` and start again (this also resets the admin account and content).
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

`composer test` runs `vendor/bin/phpunit`. `GreeterTest` checks the greeting logic alone, and `CurrentDateTest` checks the date text alone. `PreviewSiteTest` starts the preview on a free port with `preview/start.sh`, checks the home page, and checks that a second start doesn't reinstall. It is skipped if `pdo_sqlite` or `proc_open` is missing.

## Notes

- No real passwords are stored in the repository. Keep your values in `.env`, which is git-ignored.
- The greeting is plain text. The shortcode escapes it with `esc_html()` before output.

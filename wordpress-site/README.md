# Ninja WordPress site

A WordPress site in Docker with:

- **`ninja-theme`**: a minimal custom theme (`wp-content/themes/ninja-theme`).
- **`ninja-hello`**: a plugin with the shortcode `[ninja_hello name="..."]`, which prints `Hello, <name>!`. With no name, or a blank one, it prints `Hello, world!`.
- **`ninja-shows`** (named **Ninja Shoes**): a shoe shop with a **mock payment gateway**. Shoes, prices, sizes, colours and stock are managed in wp-admin. The shop is the `[ninja_shoes]` shortcode, on the **Shoes** page.
- **PHPUnit tests** for the greeting, money, card, checkout and mock payment logic. They run without a WordPress install.

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
├── tests/                      # PHPUnit: Greeter, Money, CardValidator, ShoeFields, Checkout, MockGateway, PreviewSite
└── wp-content/
    ├── plugins/ninja-hello/
    │   ├── ninja-hello.php           # plugin file, registers the shortcode
    │   └── includes/Greeter.php      # greeting logic (no WordPress calls)
    ├── plugins/ninja-shows/          # the shoe shop (plugin name: Ninja Shoes)
    │   ├── ninja-shows.php           # plugin file, loads the classes and hooks
    │   └── includes/                 # Money, CardValidator, ShoeFields, Checkout (no WordPress calls)
    │       ├── Payments/             # PaymentGateway interface, MockGateway, request and result
    │       ├── PostTypes.php         # Shoe and Order post types, admin fields
    │       ├── Setup.php             # on activation: Shoes page and sample shoes
    │       └── Shop.php              # [ninja_shoes] shortcode, checkout and confirmation
    └── themes/ninja-theme/           # style.css, index.php, functions.php
```

## Shoe shop (ninja-shows)

The shop sells shoes. Payments go through a mock gateway, so no real money moves. When the real gateway is ready, it replaces the mock without changing the checkout.

- **Shoes** are a post type in wp-admin. Each listing is one shoe in one size, with a price (USD, e.g. `89.00`), a size (for example `US 9`, `UK 8.5` or `EU 43`, up to 20 characters), an optional colour, and the number of pairs in stock. Set pairs in stock to 0 to mark a size sold out. To sell a shoe in several sizes, add one listing per size.
- The **Shoes** page holds `[ninja_shoes]`. It lists the shoes, opens checkout with `?ninja_shoe=ID`, and shows the confirmation at `?ninja_order=KEY`.
- **Checkout** asks for name, email, number of pairs (up to 10 and no more than are left in stock), and card details. The card number must pass the Luhn check and the expiry date must not have passed.
- **Mock test cards**: `4242 4242 4242 4242` is approved. `4000 0000 0000 0002` is declined (card_declined). `4000 0000 0000 9995` is declined (insufficient_funds). Any other Luhn-valid number is approved.
- Orders are stored as **Orders** in wp-admin, with the size bought. No card number or security code is stored, only the last four digits.
- To use a real gateway later, implement `NinjaShows\Payments\PaymentGateway` and return it from the `ninja_shows_payment_gateway` filter.
- Known limits: the stock is not locked, so two buyers at the same moment could both get the last pair. Activating the plugin adds four sample shoes, if the site has no shoes yet. Sites set up with the older show-ticket version keep their show data, which the shop no longer lists, and the old `[ninja_shows]` page needs to be replaced by the Shoes page.

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

# WPRuby Shipping Rates for Canada Post and WooCommerce

Free wordpress.org plugin: live Canada Post rates at WooCommerce checkout, from Canada Post's Rating API (Developer Portal, OAuth 2.0 client credentials).

- PHP 7.4+, WordPress 6.2+, WooCommerce 8.2+. No runtime dependencies.
- Method ID `canada_post`; zone settings live in `woocommerce_canada_post_{instance}_settings`.
- Store-wide settings: WooCommerce → Settings → Shipping → Canada Post (option `woocommerce_canada_post_settings_settings`).

## Layout

- `wpruby-shipping-rates-canada-post.php`: bootstrap (conflict check, autoloader, HPOS and Blocks compatibility).
- `src/Api/Client.php`: token and Get Rates (`POST /rating/v1/prices`), with retries and error parsing.
- `src/Rates/`: request building and validation, packing, per-parcel quoting and caching.
- `src/Shipping/Method.php`: the zone method; `src/Shipping/SettingsSection.php`: the settings section.
- `src/Admin/`: Test connection (AJAX).

## Development

```bash
composer install --working-dir=vendor-bin/phpunit
make test
make pack   # zip of HEAD without the export-ignored files
```

The tests run without WordPress (`tests/bootstrap.php` stubs the WordPress and WooCommerce functions used, and queues HTTP responses).

wordpress.org assets (banners, icon, screenshots) go in `.wordpress-org/`, which is uploaded to SVN `/assets/` and kept out of the zip.

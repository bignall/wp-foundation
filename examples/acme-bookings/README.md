# Acme Bookings (example)

A small plugin showing every part of WP Foundation: requirements, options, a custom role and
capability, cron events with a custom schedule, a custom table, a `1.1.0` migration, rewrite
flushing for a post type, a customised deactivation survey, and uninstall that respects a
"remove data" setting.

```bash
cd examples/acme-bookings
composer install    # Pulls the library from ../.. and prefixes it with Strauss.
```

Then copy or symlink the folder into `wp-content/plugins/` and activate it.

Files:

- [`acme-bookings.php`](acme-bookings.php): main file, with the PHP version guard.
- [`src/Bootstrap.php`](src/Bootstrap.php): the full lifecycle definition.
- [`src/BookingsTable.php`](src/BookingsTable.php): table schema.
- [`src/Migrations.php`](src/Migrations.php): a migration that adds a column and backfills data.
- [`src/App.php`](src/App.php): the plugin's own code, started from `onReady()`.

# Acme Bookings (example)

A small plugin showing every part of WP Foundation: requirements, options (with new keys merged
into existing sites), a custom role and capability, cron events with a custom schedule, a custom
table, a `1.1.0` migration that also reaches sites from before the plugin used the framework,
rewrite flushing for a post type (including after a settings change), post and term cleanup, a
flash notice after an admin action, a customised deactivation survey, and uninstall that
respects a "remove data" setting.

```bash
cd examples/acme-bookings
composer install    # Pulls the library from ../.. and prefixes it with Strauss.
```

Then copy or symlink the folder into `wp-content/plugins/` and activate it.

`composer install` runs Strauss, which rewrites the `use Bignall\WPFoundation\...` lines in `src/`
to `use AcmeBookings\Vendor\Bignall\WPFoundation\...`. In your own plugin, commit those rewritten
lines. This example keeps the unprefixed lines in git so it reads like the docs; run
`git checkout src` to undo the rewrite after trying it.

To build a release zip:

```bash
bin/build-release.sh    # build/acme-bookings.zip
```

Files:

- [`acme-bookings.php`](acme-bookings.php): main file, with the PHP version guard.
- [`src/Bootstrap.php`](src/Bootstrap.php): the full lifecycle definition.
- [`src/BookingsTable.php`](src/BookingsTable.php): table schema.
- [`src/Migrations.php`](src/Migrations.php): a migration that adds a column and backfills data.
- [`src/App.php`](src/App.php): the plugin's own code, started from `onReady()`, including a
  rewrite flush request and a flash notice.
- [`bin/build-release.sh`](bin/build-release.sh): builds a prefixed release zip.

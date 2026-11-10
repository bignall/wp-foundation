# WP Foundation

[![CI](https://github.com/bignall/wp-foundation/actions/workflows/ci.yml/badge.svg)](https://github.com/bignall/wp-foundation/actions/workflows/ci.yml)
[![License: GPL v2+](https://img.shields.io/badge/License-GPL%20v2%2B-blue.svg)](LICENSE)

**Website and docs: https://bignall.github.io/wp-foundation/**

A Composer library that handles the parts of a WordPress plugin that every plugin needs and few
get right: **requirement checks, activation, deactivation, upgrades with migrations, uninstall
cleanup, multisite**, and an optional **deactivation survey** that asks users why they're leaving.

```php
Plugin::create( __FILE__ )
	->requires( php: '8.1', wp: '6.4', plugins: [ 'woocommerce/woocommerce.php' => 'WooCommerce' ] )
	->options( [ 'acme_settings' => [ 'enabled' => true ] ] )
	->role( 'acme_manager', 'Acme Manager', [ 'read' => true ] )
	->capabilities( [ 'administrator' => [ 'manage_acme' ] ] )
	->cron( 'acme_daily_sync', 'daily' )
	->tables( new OrdersTable() )
	->migration( '1.2.0', [ Migrations::class, 'splitAddressField' ] )
	->flushRewriteRules()
	->deactivationSurvey()
	->deleteDataOnUninstall( fn () => (bool) get_option( 'acme_remove_data' ) )
	->onReady( fn ( Plugin $plugin ) => ( new Acme\App( $plugin ) )->run() )
	->boot();
```

## What it handles

| When | What happens |
| --- | --- |
| **Activation** | Checks requirements and refuses to activate with a clear message if they aren't met. Then creates tables, adds default options without overwriting existing ones, creates roles and capabilities, schedules cron events, flushes rewrite rules, and registers the uninstall hook. |
| **Every request** | Checks requirements again (a required plugin might have been deactivated). If they're not met, shows an admin notice instead of fataling. Detects new versions, including auto-updates, which never fire the activation hook, and runs the upgrade under a lock. |
| **Upgrade** | Runs versioned migrations in order, syncs table schemas with dbDelta, adds new options (and, if you ask, new keys in array options), caps and cron events. If a migration fails, the version only advances past steps that completed, an admin notice is shown, and it retries on the next page load. Plugins that shipped before adopting the framework can report their old version, so existing sites get their migrations. |
| **Deactivation** | Unschedules cron events and removes the plugin's rewrite rules. Data is kept. Optionally asks why the user is deactivating. |
| **Uninstall** | Removes tables, options, roles, capabilities, cron events, and the posts and terms of your post types and taxonomies, but only if you opt in (or let the site owner choose). |
| **Multisite** | Network activation, deactivation and uninstall run on every site. New sites are set up automatically, and deleted sites have their tables dropped. |

## Requirements

- PHP 8.1+
- WordPress 6.4+

## Installation

```bash
composer require bignall/wp-foundation
```

Because several plugins on one site may bundle different versions of this library, **prefix
it** with [Strauss](https://github.com/BrianHenryIE/strauss) before you ship. See
[docs/prefixing.md](docs/prefixing.md) and the [example plugin](examples/acme-bookings). For
release zips without Composer's dev tools, see [docs/releasing.md](docs/releasing.md).

While the library is below 1.0, depend on `^0.1`: Composer treats each 0.x minor version as a
possible breaking change.

## Quick start

```php
<?php
/**
 * Plugin Name: Acme
 * Version:     1.0.0
 * Requires PHP: 8.1
 */

defined( 'ABSPATH' ) || exit;

// Guard before loading PHP 8.1 code, so old hosts get a notice instead of a fatal error.
if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	return;
}

require_once __DIR__ . '/vendor/autoload.php';

Bignall\WPFoundation\Plugin::create( __FILE__ )
	->requires( wp: '6.4' )
	->options( [ 'acme_settings' => [] ] )
	->deactivationSurvey()
	->onReady( fn () => ( new Acme\App() )->run() )
	->boot();
```

Don't add an `uninstall.php`: the framework registers its own uninstall hook. If WordPress finds
an `uninstall.php`, it uses that file instead.

The plugin version comes from the `Version:` header. Bump it on every release and the
framework runs the upgrade.

## Deactivation survey

When a user clicks **Deactivate**, a modal asks why:

- It's a temporary deactivation, I'm troubleshooting
- I found a better plugin (*which one?*)
- I no longer need the plugin
- It isn't working or broke my site (*what went wrong?*)
- It's missing a feature I need (*which feature?*)
- It's too hard to set up or use (*what was confusing?*)
- Other (*please tell us more*)

The survey is always skippable, and deactivation goes ahead even if sending the response fails.
Responses are stored on the site by default. Add a `RemoteHandler`, or your own `FeedbackHandler`,
to send them to your server. When data leaves the site, the modal says so, and the site URL is
only included if the user ticks a consent box. See [docs/deactivation-survey.md](docs/deactivation-survey.md).

## Helpers

- `$plugin->requestRewriteFlush()` rebuilds rewrite rules at the end of the next `init`, for example
  after a settings page changes a URL prefix.
- `$plugin->notices()->flash( 'success', 'Saved.' )` shows a notice on the user's next admin page,
  for forms that redirect after saving. Detail items are plain text, or
  `array( 'text' => ..., 'url' => ... )` to print an escaped link.

## Documentation

- [Getting started](docs/getting-started.md)
- [Lifecycle and upgrades](docs/lifecycle.md)
- [Requirements](docs/requirements.md)
- [Built-in tasks and custom tasks](docs/tasks.md)
- [Database tables and migrations](docs/database.md)
- [Deactivation survey](docs/deactivation-survey.md)
- [Multisite](docs/multisite.md)
- [Flash notices](docs/notices.md)
- [Prefixing with Strauss](docs/prefixing.md)
- [Release builds](docs/releasing.md)
- [Hooks reference](docs/hooks.md)

## Development

```bash
composer install
composer test:unit          # Brain Monkey unit tests
composer lint               # WordPress Coding Standards
composer analyse            # PHPStan

# Integration tests against a real WordPress and MySQL
bin/install-wp-tests.sh wordpress_tests root root 127.0.0.1 latest
WP_TESTS_DIR=/tmp/wordpress-develop/tests/phpunit composer test:integration
WP_MULTISITE=1 WP_TESTS_DIR=/tmp/wordpress-develop/tests/phpunit composer test:integration

# Browser test for the survey modal
npm install && npx playwright install chromium && npm run test:browser

# Website (built from site/ and docs/ into _site/, deployed to GitHub Pages)
npm run site:build
```

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

GPL-2.0-or-later, the same license as WordPress.

# Getting started

## 1. Require the library

```bash
composer require arbe/wp-foundation
```

## 2. Describe your plugin in its main file

```php
<?php
/**
 * Plugin Name:       Acme Bookings
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 */

defined( 'ABSPATH' ) || exit;

if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	return; // Optionally show an admin notice here; keep this file free of PHP 8 syntax.
}

require_once __DIR__ . '/vendor/autoload.php';

use Arbe\WPFoundation\Plugin;

Plugin::create( __FILE__ )
	->requires( php: '8.1', wp: '6.4' )
	->options( [ 'acme_bookings_settings' => [ 'slot_minutes' => 30 ] ] )
	->tables( new AcmeBookings\BookingsTable() )
	->cron( 'acme_bookings_reminders', 'hourly' )
	->deactivationSurvey()
	->onReady( fn ( Plugin $plugin ) => ( new AcmeBookings\App( $plugin ) )->run() )
	->boot();
```

`boot()` registers the activation and deactivation hooks, so it has to run when WordPress loads
the main file, not inside another hook.

## 3. Put your plugin's code in `onReady()`

`onReady()` callbacks run on `plugins_loaded`, but only once the requirements pass and any
pending upgrade has finished. If a requirement fails at runtime (for example, a required plugin
was deactivated), your code doesn't run and administrators see a notice explaining why.

## 4. Turn off Composer's platform check

Composer's generated `platform_check.php` calls `exit` on old PHP versions, which takes the whole
site down. Your main-file guard already handles this, so disable it:

```json
"config": { "platform-check": false }
```

## 5. Prefix before release

See [prefixing.md](prefixing.md).

## Identity and naming

| Accessor | Example | Used for |
| --- | --- | --- |
| `basename()` | `acme-bookings/acme-bookings.php` | WordPress plugin APIs |
| `slug()` | `acme-bookings` | Script handles |
| `prefix()` | `acme_bookings` | Option names and hook names |
| `optionName( 'version' )` | `acme_bookings_version` | Framework-owned options |

Pass a second argument to `Plugin::create( __FILE__, 'acme' )` to choose the prefix yourself.

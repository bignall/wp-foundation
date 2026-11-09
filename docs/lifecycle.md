# Lifecycle and upgrades

## Activation

1. Requirements are checked. If any fail, the plugin is deactivated again and WordPress shows the
   list of problems with a "Go back" link.
2. The uninstall hook is registered.
3. If the database holds an older version (the files were updated while the plugin was inactive),
   the upgrade runs first.
4. Every task's `activate()` runs, in registration order.
5. On first install, `{prefix}_version` and `{prefix}_installed_at` are stored.
6. `{prefix}_activated` fires with `( Plugin $plugin, bool $firstTime )`.

Activation runs again on every reactivation, so tasks are idempotent: options are added only if
missing, tables are synced by dbDelta, and cron events are scheduled only if absent.

## Runtime and upgrades

On `plugins_loaded` (priority 5):

1. Requirements are checked. If any fail, an admin notice is shown and nothing else happens.
2. If the stored version is older than the code's version, the upgrade runs:
   1. Migrations newer than the stored version, up to the current version, run in version order.
      After each version's migrations finish, `{prefix}_version` advances to that version.
   2. Every task's `upgrade()` runs. For built-in tasks this re-runs activation: new tables or
      columns, new options, caps and cron events.
   3. `{prefix}_version` is set to the current version and `{prefix}_upgraded` fires.
3. `onReady()` callbacks run, then `{prefix}_ready` fires.

Upgrades run under a lock (`{prefix}_upgrade_lock`), so concurrent requests don't run migrations
twice. A lock older than 10 minutes is treated as stale.

If a migration throws, the upgrade stops and the stored version stays at the last version that
completed. The error is saved in `{prefix}_upgrade_error` and shown to administrators, and the
upgrade retries on the next page load. `onReady()` callbacks still run, so make your code
tolerate the previous schema, or check `get_option( $plugin->optionName( 'upgrade_error' ) )`.

Running the upgrade at runtime, not only on activation, matters because WordPress doesn't fire
the activation hook for updates, including automatic background updates.

If the stored version is *newer* than the code (a downgrade), nothing runs.

If the plugin is active but was never activated through WordPress (for example, enabled directly
in the database or deployed as a must-use plugin), the first request installs it.

## Deactivation

Tasks' `deactivate()` methods run in reverse order: cron events are unscheduled and rewrite rules
are dropped. Roles and capabilities are kept unless you passed `removeOnDeactivate: true`. Data is
kept. `{prefix}_deactivated` fires.

## Uninstall

WordPress calls the registered `Plugin::handleUninstall()` after loading your main file, so your
`Plugin` definition is available.

Nothing is deleted unless you opt in:

```php
->deleteDataOnUninstall()                                         // Always.
->deleteDataOnUninstall( fn () => get_option( 'acme_remove_data' ) ) // Let the site owner decide.
```

The `{prefix}_delete_data_on_uninstall` filter has the final say.

When data deletion is enabled, every task's `uninstall()` runs in reverse order on every site.
That drops tables, deletes options, removes roles and capabilities, and unschedules cron events.
The framework's own options are deleted too, then `{prefix}_uninstalled` fires.

### Using your own uninstall.php

If you need one, load the main file and hand off to the framework:

```php
<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
require_once __DIR__ . '/acme-bookings.php';
Arbe\WPFoundation\Plugin::instance( 'acme-bookings/acme-bookings.php' )?->lifecycle()->uninstall();
```

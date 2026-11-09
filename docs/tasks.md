# Tasks

Every setup step is a **task** with five methods:

| Method | Runs |
| --- | --- |
| `register( Plugin )` | On every request, when the plugin boots. Use it to add hooks the task depends on (e.g. `cron_schedules`). |
| `activate( Plugin )` | On activation, and on first install detected at runtime. |
| `deactivate( Plugin )` | On deactivation. Keep user data. |
| `upgrade( Plugin, $from, $to )` | When the stored version is older, after migrations. |
| `uninstall( Plugin )` | On uninstall, when data deletion is enabled. Remove everything the task created. |

On multisite, each method runs once per site, with that site switched in.

## Built-in tasks

### Options

```php
->options( [ 'acme_settings' => [ 'mode' => 'simple' ] ] )         // Not autoloaded.
->options( [ 'acme_small_flag' => 1 ], autoload: true )
```

Uses `add_option()`, so existing values are never overwritten. Options added in a later version
are created on upgrade.

### Roles and capabilities

```php
->role( 'acme_manager', 'Acme Manager', [ 'read' => true, 'manage_acme' => true ] )
->capabilities( [ 'administrator' => [ 'manage_acme' ], 'editor' => [ 'manage_acme' ] ] )
->capabilities( [ 'shop_manager' => [ 'manage_acme' ] ], removeOnDeactivate: true )
```

Capabilities added to an existing custom role in a new version are granted on upgrade. Roles
and caps are removed on uninstall, and also on deactivation if any `capabilities()` call passed
`removeOnDeactivate: true`.

### Cron

```php
->cronSchedule( 'every_five_minutes', 5 * MINUTE_IN_SECONDS, 'Every five minutes' )
->cron( 'acme_sync', 'every_five_minutes' )
->cron( 'acme_report', 'daily', [ 'weekly' => false ] )
```

Events are scheduled on activation and unscheduled on deactivation. If you change an event's
recurrence in a new version, it's rescheduled on upgrade. Custom schedules are registered on
every request, so WordPress can always reschedule them.

### Rewrite rules

```php
->flushRewriteRules()
```

The activation hook runs before your post types are registered, so flushing there would miss
them. Instead, a flag is set on activation and upgrade, and the flush happens at the end of the
next `init`. On deactivation, the stored rules are deleted, so WordPress rebuilds them without
yours.

### Tables

See [database.md](database.md).

## Custom tasks

Extend `AbstractTask` and override only what you need. `upgrade()` defaults to calling
`activate()`.

```php
use Bignall\WPFoundation\Plugin;
use Bignall\WPFoundation\Tasks\AbstractTask;

final class UploadsDirectoryTask extends AbstractTask {
	public function activate( Plugin $plugin ): void {
		wp_mkdir_p( $this->dir() );
		file_put_contents( $this->dir() . '/index.php', '<?php // Silence.' );
	}

	public function uninstall( Plugin $plugin ): void {
		global $wp_filesystem;
		WP_Filesystem();
		$wp_filesystem->delete( $this->dir(), true );
	}

	private function dir(): string {
		return wp_upload_dir()['basedir'] . '/acme';
	}
}

Plugin::create( __FILE__ )->task( new UploadsDirectoryTask() );
```

# Database tables and migrations

## Tables

Describe each table's **current** shape by extending `Table`:

```php
use Arbe\WPFoundation\Database\Table;

final class BookingsTable extends Table {
	public function name(): string {
		return 'acme_bookings'; // The site prefix is added for you: wp_acme_bookings, wp_2_acme_bookings, ...
	}

	public function columns(): string {
		return "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
starts_at datetime NOT NULL,
status varchar(20) NOT NULL DEFAULT 'confirmed',
PRIMARY KEY  (id),
KEY starts_at (starts_at)";
	}
}

Plugin::create( __FILE__ )->tables( new BookingsTable() );
```

On activation and upgrade, `dbDelta()` creates missing tables and adds missing columns and
indexes. On uninstall (with data deletion enabled), tables are dropped. On multisite, a deleted
site's tables are dropped along with it.

dbDelta is strict about formatting:

- One column or key per line.
- Use `KEY`, not `INDEX`.
- Two spaces after `PRIMARY KEY`.
- Lowercase types, e.g. `bigint(20)`, `varchar(191)`.

dbDelta **never drops or renames columns** and doesn't reliably change column types. Use a
migration for those.

`Table` also provides `tableName()`, `tableNameForSite( $id )`, `exists()`, `install()` and `drop()`.

## Migrations

A migration is a one-time change tied to the version that introduced it:

```php
->migration( '1.2.0', function ( Plugin $plugin ): void {
	global $wpdb;
	$table = ( new BookingsTable() )->tableName();
	$wpdb->query( "ALTER TABLE {$table} CHANGE `when` starts_at datetime NOT NULL" );
} )
```

Or as a class implementing `Arbe\WPFoundation\Database\Migration` (`version()` and `up()`),
registered with `->migrations( new RenameWhenColumn(), new BackfillStatus() )`.

### How they run

- Only on **upgrade**: migrations newer than the stored version, and no newer than the current
  version, run in version order. Migrations sharing a version run in the order you added them.
- **Not on a fresh install**, because tables are created from their current schema.
- **Before** tables are synced with dbDelta. A migration sees the previous schema, so if it needs
  a new column, it should add that column itself. dbDelta then finds the column already there.
  This order also lets renames work: rename the column in a migration, and dbDelta won't add an
  empty copy under the new name.
- After each version's migrations finish, the stored version advances. If a migration throws,
  the next request resumes from there.

Make migrations safe to re-run where you can (check whether a column exists before adding it,
for example). An interrupted request can repeat the version that was in progress.

<?php
/**
 * Data migrations.
 *
 * @package AcmeBookings
 */

declare(strict_types=1);

namespace AcmeBookings;

use Bignall\WPFoundation\Plugin;

/**
 * Migrations run in version order when a site upgrades.
 */
final class Migrations {

	/**
	 * 1.1.0: add the status column and mark past bookings as completed.
	 *
	 * Migrations run before dbDelta syncs the schema, so a migration that needs a new column
	 * adds it itself; dbDelta then sees it already exists.
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public static function backfillStatus( Plugin $plugin ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Part of the migration signature.
		global $wpdb;
		$table = ( new BookingsTable() )->tableName();

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names can't be placeholders.
		$columns = $wpdb->get_col( "DESCRIBE {$table}" );
		if ( ! in_array( 'status', $columns, true ) ) {
			$wpdb->query( "ALTER TABLE {$table} ADD status varchar(20) NOT NULL DEFAULT 'confirmed'" );
		}
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'completed' WHERE ends_at < %s", current_time( 'mysql', true ) ) );
		// phpcs:enable
	}
}

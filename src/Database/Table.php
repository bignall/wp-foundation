<?php
/**
 * Custom database table.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Database;

/**
 * A custom table managed with dbDelta.
 *
 * Extend this class and describe the table's current shape; dbDelta adds missing tables,
 * columns and indexes on activation and upgrade. It never drops or renames columns, so use a
 * migration for those.
 *
 * dbDelta is strict about formatting: one column per line, the key word `KEY` (not `INDEX`),
 * and two spaces after `PRIMARY KEY`.
 *
 *     final class BookingsTable extends Table {
 *         public function name(): string { return 'acme_bookings'; }
 *         public function columns(): string {
 *             return "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
 *                 starts_at datetime NOT NULL,
 *                 PRIMARY KEY  (id),
 *                 KEY starts_at (starts_at)";
 *         }
 *     }
 */
abstract class Table {

	/**
	 * Table name without the site prefix, e.g. `acme_bookings`.
	 */
	abstract public function name(): string;

	/**
	 * Column and index definitions, the part inside `CREATE TABLE name ( ... )`.
	 */
	abstract public function columns(): string;

	/**
	 * Full table name for the current site, e.g. `wp_2_acme_bookings`.
	 */
	public function tableName(): string {
		global $wpdb;
		return $wpdb->prefix . $this->name();
	}

	/**
	 * Full table name for a given site.
	 *
	 * @param int $siteId Site ID.
	 */
	public function tableNameForSite( int $siteId ): string {
		global $wpdb;
		return $wpdb->get_blog_prefix( $siteId ) . $this->name();
	}

	/**
	 * `CREATE TABLE` statement for dbDelta.
	 */
	public function createSql(): string {
		global $wpdb;
		return sprintf(
			"CREATE TABLE %s (\n%s\n) %s;",
			$this->tableName(),
			trim( $this->columns() ),
			$wpdb->get_charset_collate()
		);
	}

	/**
	 * Create the table or bring it up to date.
	 */
	public function install(): void {
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		dbDelta( $this->createSql() );
	}

	/**
	 * Drop the table.
	 */
	public function drop(): void {
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS `{$this->tableName()}`" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Table names can't be placeholders.
	}

	/**
	 * Whether the table exists.
	 */
	public function exists(): bool {
		global $wpdb;
		$name = $this->tableName();
		return $name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $name ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}

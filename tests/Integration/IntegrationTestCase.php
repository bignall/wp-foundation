<?php
/**
 * Base integration test case.
 *
 * @package Arbe\WPFoundation
 */

declare(strict_types=1);

namespace Arbe\WPFoundation\Tests\Integration;

use Arbe\WPFoundation\Plugin;
use Arbe\WPFoundation\Tests\Integration\Fixtures\ItemsTable;
use WP_UnitTestCase;

abstract class IntegrationTestCase extends WP_UnitTestCase {

	protected const FILE = __DIR__ . '/Fixtures/fixture-plugin.php';

	public function set_up(): void {
		parent::set_up();
		Plugin::resetRegistry();

		// Use real tables, so SHOW TABLES and dbDelta behave as in production.
		remove_filter( 'query', array( $this, '_create_temporary_tables' ) );
		remove_filter( 'query', array( $this, '_drop_temporary_tables' ) );
	}

	public function tear_down(): void {
		( new ItemsTable() )->drop();
		remove_role( 'wpf_manager' );
		get_role( 'administrator' )->remove_cap( 'wpf_manage_items' );
		wp_unschedule_hook( 'wpf_cleanup' );
		parent::tear_down();
	}

	protected function columns( string $table ): array {
		global $wpdb;
		return $wpdb->get_col( "DESCRIBE {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	protected function plugin( string $version = '1.0.0', bool $withNotes = false ): Plugin {
		return Plugin::create( self::FILE, 'wpf_fixture' )
			->version( $version )
			->options( array( 'wpf_fixture_settings' => array( 'mode' => 'default' ) ) )
			->role( 'wpf_manager', 'Items Manager', array( 'read' => true ) )
			->capabilities( array( 'administrator' => array( 'wpf_manage_items' ) ) )
			->cron( 'wpf_cleanup', 'daily' )
			->tables( new ItemsTable( $withNotes ) )
			->deleteDataOnUninstall();
	}
}

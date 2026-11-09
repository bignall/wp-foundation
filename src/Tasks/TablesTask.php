<?php
/**
 * Custom tables.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Tasks;

use Bignall\WPFoundation\Database\Table;
use Bignall\WPFoundation\Plugin;

/**
 * Creates and updates custom tables with dbDelta, drops them on uninstall, and drops each
 * site's tables when the site is deleted from a network.
 */
final class TablesTask extends AbstractTask {

	/**
	 * Tables.
	 *
	 * @var list<Table>
	 */
	private array $tables = array();

	/**
	 * Add a table.
	 *
	 * @param Table $table Table.
	 */
	public function add( Table $table ): void {
		$this->tables[] = $table;
	}

	/**
	 * Tables.
	 *
	 * @return list<Table>
	 */
	public function tables(): array {
		return $this->tables;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function register( Plugin $plugin ): void {
		if ( is_multisite() ) {
			add_filter( 'wpmu_drop_tables', array( $this, 'addSiteTables' ), 10, 2 );
		}
	}

	/**
	 * Add this plugin's tables to the list dropped when a site is deleted.
	 *
	 * @param list<string> $tables Tables to drop.
	 * @param int          $siteId Site being deleted.
	 * @return list<string>
	 */
	public function addSiteTables( $tables, $siteId ): array {
		$tables = (array) $tables;
		foreach ( $this->tables as $table ) {
			$tables[] = $table->tableNameForSite( (int) $siteId );
		}
		return $tables;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function activate( Plugin $plugin ): void {
		foreach ( $this->tables as $table ) {
			$table->install();
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function uninstall( Plugin $plugin ): void {
		foreach ( array_reverse( $this->tables ) as $table ) {
			$table->drop();
		}
	}
}

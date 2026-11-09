<?php
/**
 * Migration contract.
 *
 * @package Arbe\WPFoundation
 */

declare(strict_types=1);

namespace Arbe\WPFoundation\Database;

use Arbe\WPFoundation\Plugin;

/**
 * A one-way data or schema change introduced in a given plugin version.
 *
 * Migrations run in version order when a site upgrades from an older version, before
 * tables are synced with dbDelta. They don't run on a fresh install, where tables are created
 * from their current schema. Throw an exception to stop the upgrade; it will retry on the
 * next page load from the last migration that succeeded.
 */
interface Migration {

	/**
	 * Plugin version that introduced this migration, e.g. `1.2.0`.
	 */
	public function version(): string;

	/**
	 * Apply the migration.
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function up( Plugin $plugin ): void;
}

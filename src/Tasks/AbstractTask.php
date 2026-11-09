<?php
/**
 * Base task with no-op defaults.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Tasks;

use Bignall\WPFoundation\Plugin;

/**
 * Extend this to implement only the lifecycle steps a task needs.
 */
abstract class AbstractTask implements Task {

	/**
	 * {@inheritDoc}
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function register( Plugin $plugin ): void {}

	/**
	 * {@inheritDoc}
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function activate( Plugin $plugin ): void {}

	/**
	 * {@inheritDoc}
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function deactivate( Plugin $plugin ): void {}

	/**
	 * Defaults to re-running activation, which suits idempotent tasks.
	 *
	 * @param Plugin $plugin The plugin.
	 * @param string $from   Previous version.
	 * @param string $to     New version.
	 */
	public function upgrade( Plugin $plugin, string $from, string $to ): void {
		$this->activate( $plugin );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function uninstall( Plugin $plugin ): void {}
}

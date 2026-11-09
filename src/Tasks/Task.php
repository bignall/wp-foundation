<?php
/**
 * Lifecycle task contract.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Tasks;

use Bignall\WPFoundation\Plugin;

/**
 * A unit of setup and teardown work. Each method runs on the current site; on multisite the
 * framework switches to each site in turn.
 *
 * Every method should be idempotent: activation runs on every reactivation, and upgrade can
 * run more than once if an earlier attempt was interrupted.
 */
interface Task {

	/**
	 * Register runtime hooks. Runs on every request, when the plugin boots.
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function register( Plugin $plugin ): void;

	/**
	 * Runs when the plugin is activated, and on a fresh install detected at runtime.
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function activate( Plugin $plugin ): void;

	/**
	 * Runs when the plugin is deactivated. Keep the user's data.
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function deactivate( Plugin $plugin ): void;

	/**
	 * Runs when the stored version is older than the code, after migrations.
	 *
	 * @param Plugin $plugin The plugin.
	 * @param string $from   Previous version.
	 * @param string $to     New version.
	 */
	public function upgrade( Plugin $plugin, string $from, string $to ): void;

	/**
	 * Runs on uninstall when data deletion is enabled. Remove everything the task created.
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function uninstall( Plugin $plugin ): void;
}

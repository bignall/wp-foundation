<?php
/**
 * Rewrite rules.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Tasks;

use Bignall\WPFoundation\Plugin;

/**
 * Flushes rewrite rules after activation or upgrade, and removes the plugin's rules on
 * deactivation.
 *
 * The activation hook runs after `init`, before the plugin has registered its post types, so
 * flushing there would miss them. Instead, a flush is requested with
 * `Plugin::requestRewriteFlush()` and happens late on the next `init`.
 */
final class RewriteTask extends AbstractTask {

	/**
	 * {@inheritDoc}
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function activate( Plugin $plugin ): void {
		$plugin->requestRewriteFlush();
	}

	/**
	 * Delete the stored rules so WordPress rebuilds them, without this plugin's, on the next request.
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function deactivate( Plugin $plugin ): void {
		delete_option( 'rewrite_rules' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function uninstall( Plugin $plugin ): void {
		delete_option( $plugin->optionName( 'flush_rewrite_rules' ) );
	}
}

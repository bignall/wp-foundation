<?php
/**
 * Rewrite rules.
 *
 * @package Arbe\WPFoundation
 */

declare(strict_types=1);

namespace Arbe\WPFoundation\Tasks;

use Arbe\WPFoundation\Plugin;

/**
 * Flushes rewrite rules after activation or upgrade, and removes the plugin's rules on
 * deactivation.
 *
 * The activation hook runs after `init`, before the plugin has registered its post types, so
 * flushing there would miss them. Instead, a flag is set and the flush happens late on the
 * next `init`.
 */
final class RewriteTask extends AbstractTask {

	/**
	 * {@inheritDoc}
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function register( Plugin $plugin ): void {
		add_action(
			'init',
			static function () use ( $plugin ): void {
				$flag = $plugin->optionName( 'flush_rewrite_rules' );
				if ( get_option( $flag ) ) {
					delete_option( $flag );
					flush_rewrite_rules( false );
				}
			},
			PHP_INT_MAX
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function activate( Plugin $plugin ): void {
		update_option( $plugin->optionName( 'flush_rewrite_rules' ), 1, false );
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

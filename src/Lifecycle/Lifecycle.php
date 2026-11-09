<?php
/**
 * Lifecycle manager.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Lifecycle;

use Bignall\WPFoundation\Database\Migrator;
use Bignall\WPFoundation\Plugin;
use WP_Site;

/**
 * Runs activation, deactivation, upgrade and uninstall for a plugin, on every site of a
 * network where needed.
 */
final class Lifecycle {

	/**
	 * Seconds after which an upgrade lock is considered stale.
	 */
	private const LOCK_TIMEOUT = 600;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Register WordPress hooks.
	 */
	public function register(): void {
		register_activation_hook( $this->plugin->file(), array( $this, 'activate' ) );
		register_deactivation_hook( $this->plugin->file(), array( $this, 'deactivate' ) );

		// Flushes requested by activation, upgrade or Plugin::requestRewriteFlush(). Runs late
		// on init, once post types and rewrite rules are registered.
		add_action( 'init', array( $this, 'maybeFlushRewriteRules' ), PHP_INT_MAX );

		add_action( 'admin_notices', array( $this, 'printUpgradeErrorNotice' ) );
		add_action( 'network_admin_notices', array( $this, 'printUpgradeErrorNotice' ) );

		if ( is_multisite() ) {
			// Core creates the new site's tables at priority 10.
			add_action( 'wp_initialize_site', array( $this, 'activateNewSite' ), 200 );
		}
	}

	/**
	 * Activation hook.
	 *
	 * @param bool $networkWide Whether the plugin is being network-activated.
	 */
	public function activate( bool $networkWide = false ): void {
		$errors = $this->plugin->requirements()->errors();
		if ( array() !== $errors ) {
			$this->abortActivation( $errors, $networkWide );
			return;
		}

		register_uninstall_hook( $this->plugin->file(), array( Plugin::class, 'handleUninstall' ) );

		if ( $networkWide && is_multisite() ) {
			$this->forEachSite( fn () => $this->activateSite() );
		} else {
			$this->activateSite();
		}
	}

	/**
	 * Install or reactivate the plugin on the current site.
	 */
	public function activateSite(): void {
		$stored  = $this->storedVersion();
		$current = $this->plugin->currentVersion();

		// An install from before the plugin adopted the framework is upgraded, not installed.
		$from = $stored ?? $this->plugin->detectLegacyVersion();

		// Reactivating after files were updated while the plugin was inactive.
		if ( null !== $from && version_compare( $from, $current, '<' ) ) {
			if ( ! $this->upgradeSite( $from ) ) {
				return;
			}
		}

		foreach ( $this->plugin->tasks() as $task ) {
			$task->activate( $this->plugin );
		}

		if ( null === $stored ) {
			$this->recordInstall();
		}

		/**
		 * Fires after the plugin is activated on a site.
		 *
		 * @param Plugin $plugin    The plugin.
		 * @param bool   $firstTime Whether this is the first install on the site.
		 */
		do_action( "{$this->plugin->prefix()}_activated", $this->plugin, null === $from );
	}

	/**
	 * Deactivation hook.
	 *
	 * @param bool $networkWide Whether the plugin is being network-deactivated.
	 */
	public function deactivate( bool $networkWide = false ): void {
		if ( $networkWide && is_multisite() ) {
			$this->forEachSite( fn () => $this->deactivateSite() );
		} else {
			$this->deactivateSite();
		}
	}

	/**
	 * Deactivate the plugin on the current site.
	 */
	public function deactivateSite(): void {
		foreach ( array_reverse( $this->plugin->tasks() ) as $task ) {
			$task->deactivate( $this->plugin );
		}

		/**
		 * Fires after the plugin is deactivated on a site.
		 *
		 * @param Plugin $plugin The plugin.
		 */
		do_action( "{$this->plugin->prefix()}_deactivated", $this->plugin );
	}

	/**
	 * Uninstall: remove the plugin's data from every site, if data deletion is enabled.
	 */
	public function uninstall(): void {
		if ( ! $this->plugin->shouldDeleteDataOnUninstall() ) {
			return;
		}

		$this->forEachSite( fn () => $this->uninstallSite() );

		/**
		 * Fires after the plugin's data has been removed from every site.
		 *
		 * @param Plugin $plugin The plugin.
		 */
		do_action( "{$this->plugin->prefix()}_uninstalled", $this->plugin );
	}

	/**
	 * Remove the plugin's data from the current site.
	 */
	public function uninstallSite(): void {
		foreach ( array_reverse( $this->plugin->tasks() ) as $task ) {
			$task->uninstall( $this->plugin );
		}

		foreach ( array( 'version', 'installed_at', 'upgrade_lock', 'upgrade_error', 'deactivation_feedback', 'flush_rewrite_rules' ) as $key ) {
			delete_option( $this->plugin->optionName( $key ) );
		}
	}

	/**
	 * Run an upgrade if the stored version is older than the code. Installs the plugin if it
	 * is active but was never activated through WordPress (e.g. enabled directly in the database).
	 *
	 * When no version is stored, `Plugin::legacyVersion()` is consulted: an install that predates
	 * the framework is upgraded from the version it reports, so its migrations run.
	 */
	public function maybeUpgrade(): void {
		$stored  = $this->storedVersion();
		$current = $this->plugin->currentVersion();

		if ( null !== $stored && version_compare( $stored, $current, '>=' ) ) {
			return;
		}

		if ( ! $this->acquireLock() ) {
			return;
		}

		try {
			if ( null !== $stored ) {
				$this->upgradeSite( $stored );
				return;
			}

			$legacy = $this->plugin->detectLegacyVersion();
			if ( null === $legacy ) {
				$this->activateSite();
			} elseif ( version_compare( $legacy, $current, '<' ) ) {
				if ( $this->upgradeSite( $legacy ) ) {
					$this->recordInstall();
				}
			} else {
				// Already on the current version's data: just start tracking it.
				$this->recordInstall();
			}
		} finally {
			$this->releaseLock();
		}
	}

	/**
	 * Flush rewrite rules if a flush was requested.
	 *
	 * @internal Hooked to `init`.
	 */
	public function maybeFlushRewriteRules(): void {
		$flag = $this->plugin->optionName( 'flush_rewrite_rules' );
		if ( get_option( $flag ) ) {
			delete_option( $flag );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Upgrade the current site from a previous version.
	 *
	 * @param string $from Version stored in the database.
	 * @return bool Whether the upgrade completed.
	 */
	public function upgradeSite( string $from ): bool {
		$to       = $this->plugin->currentVersion();
		$migrator = new Migrator( $this->plugin );

		$error = $migrator->run( $from, $to );
		if ( null !== $error ) {
			update_option( $this->plugin->optionName( 'upgrade_error' ), $error, false );
			return false;
		}

		foreach ( $this->plugin->tasks() as $task ) {
			$task->upgrade( $this->plugin, $from, $to );
		}

		update_option( $this->plugin->optionName( 'version' ), $to, false );
		delete_option( $this->plugin->optionName( 'upgrade_error' ) );

		/**
		 * Fires after the plugin is upgraded on a site.
		 *
		 * @param string $from   Previous version.
		 * @param string $to     New version.
		 * @param Plugin $plugin The plugin.
		 */
		do_action( "{$this->plugin->prefix()}_upgraded", $from, $to, $this->plugin );

		return true;
	}

	/**
	 * Activate the plugin on a newly created site when it is network-active.
	 *
	 * @param WP_Site $site New site.
	 */
	public function activateNewSite( WP_Site $site ): void {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active_for_network( $this->plugin->basename() ) ) {
			return;
		}

		switch_to_blog( (int) $site->blog_id );
		try {
			$this->activateSite();
		} finally {
			restore_current_blog();
		}
	}

	/**
	 * Run a callback on every site of the current network, or once on a single site.
	 *
	 * @param callable(): void $callback Callback.
	 */
	public function forEachSite( callable $callback ): void {
		if ( ! is_multisite() ) {
			$callback();
			return;
		}

		$siteIds = get_sites(
			array(
				'fields'     => 'ids',
				'number'     => 0,
				'network_id' => get_current_network_id(),
			)
		);

		/**
		 * Filters the sites a network-wide lifecycle operation runs on. Large networks can
		 * return fewer sites here and process the rest in the background.
		 *
		 * @param list<int> $siteIds Site IDs.
		 * @param Plugin    $plugin  The plugin.
		 */
		$siteIds = (array) apply_filters( "{$this->plugin->prefix()}_network_site_ids", $siteIds, $this->plugin );

		foreach ( $siteIds as $siteId ) {
			switch_to_blog( (int) $siteId );
			try {
				$callback();
			} finally {
				restore_current_blog();
			}
		}
	}

	/**
	 * Show unmet requirements as an admin notice.
	 *
	 * @param list<string> $errors Requirement errors.
	 */
	public function showRequirementErrors( array $errors ): void {
		$print = function () use ( $errors ): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			$this->printNotice(
				sprintf(
					/* translators: %s: plugin name or slug. */
					__( '%s is active but cannot run:', 'wp-foundation' ),
					$this->plugin->slug()
				),
				$errors
			);
		};

		add_action( 'admin_notices', $print );
		add_action( 'network_admin_notices', $print );
	}

	/**
	 * Show the last upgrade error to administrators.
	 */
	public function printUpgradeErrorNotice(): void {
		$error = get_option( $this->plugin->optionName( 'upgrade_error' ) );
		if ( ! is_string( $error ) || '' === $error || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->printNotice(
			sprintf(
				/* translators: %s: plugin name or slug. */
				__( '%s could not finish updating its data. It will retry on the next page load.', 'wp-foundation' ),
				$this->plugin->slug()
			),
			array( $error )
		);
	}

	/**
	 * Start tracking the install: store the current version (unless an upgrade already did)
	 * and the install time (unless already known).
	 */
	private function recordInstall(): void {
		if ( null === $this->storedVersion() ) {
			update_option( $this->plugin->optionName( 'version' ), $this->plugin->currentVersion(), false );
		}
		add_option( $this->plugin->optionName( 'installed_at' ), time(), '', false );
	}

	/**
	 * Version stored in the database for the current site, or null when not installed.
	 */
	public function storedVersion(): ?string {
		$version = get_option( $this->plugin->optionName( 'version' ) );
		return is_string( $version ) && '' !== $version ? $version : null;
	}

	/**
	 * Deactivate the plugin and stop activation with an explanation.
	 *
	 * @param list<string> $errors      Requirement errors.
	 * @param bool         $networkWide Whether this was a network activation.
	 */
	private function abortActivation( array $errors, bool $networkWide ): void {
		deactivate_plugins( $this->plugin->basename(), true, $networkWide );

		$items = implode( '', array_map( static fn ( string $e ): string => '<li>' . esc_html( $e ) . '</li>', $errors ) );

		wp_die(
			'<p>' . esc_html__( 'The plugin could not be activated:', 'wp-foundation' ) . '</p><ul>' . $items . '</ul>', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
			esc_html__( 'Plugin activation failed', 'wp-foundation' ),
			array( 'back_link' => true )
		);
	}

	/**
	 * Print an error notice.
	 *
	 * @param string       $message Heading.
	 * @param list<string> $items   Details.
	 */
	private function printNotice( string $message, array $items ): void {
		echo '<div class="notice notice-error"><p><strong>' . esc_html( $message ) . '</strong></p><ul>';
		foreach ( $items as $item ) {
			echo '<li>' . esc_html( $item ) . '</li>';
		}
		echo '</ul></div>';
	}

	/**
	 * Take the upgrade lock so concurrent requests don't run migrations twice.
	 */
	private function acquireLock(): bool {
		$name = $this->plugin->optionName( 'upgrade_lock' );

		if ( add_option( $name, time(), '', false ) ) {
			return true;
		}

		$lockedAt = (int) get_option( $name, 0 );
		if ( time() - $lockedAt > self::LOCK_TIMEOUT ) {
			update_option( $name, time(), false );
			return true;
		}

		return false;
	}

	/**
	 * Release the upgrade lock.
	 */
	private function releaseLock(): void {
		delete_option( $this->plugin->optionName( 'upgrade_lock' ) );
	}
}

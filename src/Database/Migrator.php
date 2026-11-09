<?php
/**
 * Migration runner.
 *
 * @package Arbe\WPFoundation
 */

declare(strict_types=1);

namespace Arbe\WPFoundation\Database;

use Arbe\WPFoundation\Plugin;
use Throwable;

/**
 * Runs pending migrations in version order and records progress after each one.
 */
final class Migrator {

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Migrations newer than `$from` and no newer than `$to`, in version order.
	 *
	 * @param string $from Installed version.
	 * @param string $to   Target version.
	 * @return list<Migration>
	 */
	public function pending( string $from, string $to ): array {
		$pending = array_values(
			array_filter(
				$this->plugin->migrationList(),
				static fn ( Migration $m ): bool => version_compare( $m->version(), $from, '>' )
					&& version_compare( $m->version(), $to, '<=' )
			)
		);

		// Stable sort keeps registration order for migrations sharing a version.
		usort( $pending, static fn ( Migration $a, Migration $b ): int => version_compare( $a->version(), $b->version() ) );

		return $pending;
	}

	/**
	 * Run pending migrations. After each version completes, the stored version is advanced
	 * so a failure resumes where it stopped.
	 *
	 * @param string $from Installed version.
	 * @param string $to   Target version.
	 * @return string|null Error message, or null on success.
	 */
	public function run( string $from, string $to ): ?string {
		$pending = $this->pending( $from, $to );

		foreach ( $pending as $index => $migration ) {
			try {
				$migration->up( $this->plugin );
			} catch ( Throwable $e ) {
				/**
				 * Fires when a migration fails.
				 *
				 * @param Throwable $e         The error.
				 * @param Migration $migration The failed migration.
				 * @param Plugin    $plugin    The plugin.
				 */
				do_action( "{$this->plugin->prefix()}_migration_failed", $e, $migration, $this->plugin );

				return sprintf( 'Migration %s failed: %s', $migration->version(), $e->getMessage() );
			}

			$next = $pending[ $index + 1 ] ?? null;
			if ( null === $next || $next->version() !== $migration->version() ) {
				update_option( $this->plugin->optionName( 'version' ), $migration->version(), false );
			}
		}

		return null;
	}
}

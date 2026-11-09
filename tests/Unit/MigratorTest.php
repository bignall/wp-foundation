<?php
/**
 * Migrator tests.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Tests\Unit;

use Bignall\WPFoundation\Database\CallbackMigration;
use Bignall\WPFoundation\Database\Migrator;
use Brain\Monkey\Actions;
use RuntimeException;

final class MigratorTest extends TestCase {

	public function test_pending_is_filtered_and_sorted(): void {
		$noop   = static function (): void {};
		$plugin = $this->plugin()->migrations(
			new CallbackMigration( '1.10.0', $noop ),
			new CallbackMigration( '1.2.0', $noop ),
			new CallbackMigration( '1.0.0', $noop ),
			new CallbackMigration( '2.1.0', $noop ),
			new CallbackMigration( '1.2.0', $noop ),
		);

		$versions = array_map( static fn ( $m ) => $m->version(), ( new Migrator( $plugin ) )->pending( '1.0.0', '2.0.0' ) );

		self::assertSame( array( '1.2.0', '1.2.0', '1.10.0' ), $versions );
	}

	public function test_run_advances_stored_version_after_each_version(): void {
		$seen                                   = array();
		$plugin                                 = $this->plugin()
			->migration(
				'1.1.0',
				function () use ( &$seen ): void {
					$seen[] = '1.1.0:' . get_option( 'acme_bookings_version' );
				}
			)
			->migration(
				'1.2.0',
				function () use ( &$seen ): void {
					$seen[] = '1.2.0:' . get_option( 'acme_bookings_version' );
				}
			);
		$this->options['acme_bookings_version'] = '1.0.0';

		self::assertNull( ( new Migrator( $plugin ) )->run( '1.0.0', '1.2.0' ) );
		self::assertSame( array( '1.1.0:1.0.0', '1.2.0:1.1.0' ), $seen );
		self::assertSame( '1.2.0', $this->options['acme_bookings_version'] );
	}

	public function test_failure_stops_and_keeps_last_successful_version(): void {
		$ranAfterFailure = false;
		$plugin          = $this->plugin()
			->migration( '1.1.0', static function (): void {} )
			->migration(
				'1.2.0',
				static function (): void {
					throw new RuntimeException( 'boom' );
				}
			)
			->migration(
				'1.3.0',
				function () use ( &$ranAfterFailure ): void {
					$ranAfterFailure = true;
				}
			);
		Actions\expectDone( 'acme_bookings_migration_failed' )->once();

		$error = ( new Migrator( $plugin ) )->run( '1.0.0', '1.3.0' );

		self::assertSame( 'Migration 1.2.0 failed: boom', $error );
		self::assertFalse( $ranAfterFailure );
		self::assertSame( '1.1.0', $this->options['acme_bookings_version'] );
	}
}

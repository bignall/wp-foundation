<?php
/**
 * Lifecycle tests.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Tests\Unit;

use ArrayObject;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use RuntimeException;

final class LifecycleTest extends TestCase {

	public function test_first_activation_installs(): void {
		Functions\expect( 'register_uninstall_hook' )->once();
		Actions\expectDone( 'acme_bookings_activated' )->once()->with( \Mockery::any(), true );

		$task = new RecordingTask();
		$this->plugin()->task( $task )->lifecycle()->activate();

		self::assertSame( array( 'activate' ), $task->calls );
		self::assertSame( '1.0.0', $this->options['acme_bookings_version'] );
		self::assertArrayHasKey( 'acme_bookings_installed_at', $this->options );
	}

	public function test_activation_with_unmet_requirements_deactivates_and_dies(): void {
		Functions\expect( 'deactivate_plugins' )->once()->with( 'acme-bookings/acme-bookings.php', true, false );
		Functions\expect( 'wp_die' )->once();
		Functions\expect( 'register_uninstall_hook' )->never();

		$task = new RecordingTask();
		$this->plugin()->requires( wp: '99.0' )->task( $task )->lifecycle()->activate();

		self::assertSame( array(), $task->calls );
	}

	public function test_reactivation_after_update_runs_upgrade_first(): void {
		Functions\when( 'register_uninstall_hook' )->justReturn( null );
		$this->headerVersion                    = '1.1.0';
		$this->options['acme_bookings_version'] = '1.0.0';

		$log  = new ArrayObject();
		$task = new RecordingTask( 'task', $log );
		$this->plugin()
			->task( $task )
			->migration(
				'1.1.0',
				static function () use ( $log ): void {
					$log->append( 'migration:1.1.0' );
				}
			)
			->lifecycle()
			->activate();

		self::assertSame( array( 'migration:1.1.0', 'task:upgrade 1.0.0->1.1.0', 'task:activate' ), $log->getArrayCopy() );
		self::assertSame( '1.1.0', $this->options['acme_bookings_version'] );
	}

	public function test_maybe_upgrade_is_noop_when_current(): void {
		$this->options['acme_bookings_version'] = '1.0.0';
		$task                                   = new RecordingTask();

		$this->plugin()->task( $task )->lifecycle()->maybeUpgrade();

		self::assertSame( array(), $task->calls );
	}

	public function test_maybe_upgrade_ignores_downgrade(): void {
		$this->options['acme_bookings_version'] = '2.0.0';
		$task                                   = new RecordingTask();

		$this->plugin()->task( $task )->lifecycle()->maybeUpgrade();

		self::assertSame( array(), $task->calls );
		self::assertSame( '2.0.0', $this->options['acme_bookings_version'] );
	}

	public function test_maybe_upgrade_installs_when_never_activated(): void {
		$task = new RecordingTask();

		$this->plugin()->task( $task )->lifecycle()->maybeUpgrade();

		self::assertSame( array( 'activate' ), $task->calls );
		self::assertSame( '1.0.0', $this->options['acme_bookings_version'] );
		self::assertArrayNotHasKey( 'acme_bookings_upgrade_lock', $this->options );
	}

	public function test_maybe_upgrade_upgrades_and_fires_action(): void {
		$this->headerVersion                    = '1.2.0';
		$this->options['acme_bookings_version'] = '1.0.0';
		Actions\expectDone( 'acme_bookings_upgraded' )->once()->with( '1.0.0', '1.2.0', \Mockery::any() );

		$task = new RecordingTask();
		$this->plugin()->task( $task )->lifecycle()->maybeUpgrade();

		self::assertSame( array( 'upgrade 1.0.0->1.2.0' ), $task->calls );
		self::assertSame( '1.2.0', $this->options['acme_bookings_version'] );
	}

	public function test_maybe_upgrade_respects_fresh_lock(): void {
		$this->headerVersion                         = '1.2.0';
		$this->options['acme_bookings_version']      = '1.0.0';
		$this->options['acme_bookings_upgrade_lock'] = time();

		$task = new RecordingTask();
		$this->plugin()->task( $task )->lifecycle()->maybeUpgrade();

		self::assertSame( array(), $task->calls );
	}

	public function test_maybe_upgrade_takes_over_stale_lock(): void {
		$this->headerVersion                         = '1.2.0';
		$this->options['acme_bookings_version']      = '1.0.0';
		$this->options['acme_bookings_upgrade_lock'] = time() - 3600;

		$task = new RecordingTask();
		$this->plugin()->task( $task )->lifecycle()->maybeUpgrade();

		self::assertSame( array( 'upgrade 1.0.0->1.2.0' ), $task->calls );
	}

	public function test_failed_migration_records_error_and_skips_tasks(): void {
		$this->headerVersion                    = '1.2.0';
		$this->options['acme_bookings_version'] = '1.0.0';

		$task = new RecordingTask();
		$this->plugin()
			->task( $task )
			->migration(
				'1.2.0',
				static function (): void {
					throw new RuntimeException( 'disk full' );
				}
			)
			->lifecycle()
			->maybeUpgrade();

		self::assertSame( array(), $task->calls );
		self::assertSame( '1.0.0', $this->options['acme_bookings_version'] );
		self::assertSame( 'Migration 1.2.0 failed: disk full', $this->options['acme_bookings_upgrade_error'] );
		self::assertArrayNotHasKey( 'acme_bookings_upgrade_lock', $this->options );
	}

	public function test_deactivation_runs_tasks_in_reverse(): void {
		$log = new ArrayObject();
		$this->plugin()
			->task( new RecordingTask( 'first', $log ) )
			->task( new RecordingTask( 'second', $log ) )
			->lifecycle()
			->deactivate();

		self::assertSame( array( 'second:deactivate', 'first:deactivate' ), $log->getArrayCopy() );
	}

	public function test_uninstall_keeps_data_by_default(): void {
		$this->options['acme_bookings_version'] = '1.0.0';
		$task                                   = new RecordingTask();

		$this->plugin()->task( $task )->lifecycle()->uninstall();

		self::assertSame( array(), $task->calls );
		self::assertSame( '1.0.0', $this->options['acme_bookings_version'] );
	}

	public function test_uninstall_removes_data_when_enabled(): void {
		$this->options['acme_bookings_version']               = '1.0.0';
		$this->options['acme_bookings_deactivation_feedback'] = array();
		$this->options['unrelated']                           = 'keep';
		Actions\expectDone( 'acme_bookings_uninstalled' )->once();

		$task = new RecordingTask();
		$this->plugin()->task( $task )->deleteDataOnUninstall()->lifecycle()->uninstall();

		self::assertSame( array( 'uninstall' ), $task->calls );
		self::assertSame( array( 'unrelated' => 'keep' ), $this->options );
	}

	public function test_network_activation_runs_on_every_site(): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'register_uninstall_hook' )->justReturn( null );
		Functions\when( 'get_current_network_id' )->justReturn( 1 );
		Functions\expect( 'get_sites' )->once()->andReturn( array( 1, 2, 3 ) );
		Functions\expect( 'switch_to_blog' )->times( 3 );
		Functions\expect( 'restore_current_blog' )->times( 3 );

		$task = new RecordingTask();
		$this->plugin()->task( $task )->lifecycle()->activate( true );

		self::assertSame( array( 'activate', 'activate', 'activate' ), $task->calls );
	}

	public function test_network_site_list_is_filterable(): void {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'get_current_network_id' )->justReturn( 1 );
		Functions\when( 'get_sites' )->justReturn( array( 1, 2, 3 ) );
		Functions\expect( 'switch_to_blog' )->once()->with( 2 );
		Functions\when( 'restore_current_blog' )->justReturn( true );
		\Brain\Monkey\Filters\expectApplied( 'acme_bookings_network_site_ids' )->once()->andReturn( array( 2 ) );

		$task = new RecordingTask();
		$this->plugin()->task( $task )->lifecycle()->deactivate( true );

		self::assertSame( array( 'deactivate' ), $task->calls );
	}

	public function test_new_site_is_activated_when_network_active(): void {
		Functions\when( 'is_plugin_active_for_network' )->justReturn( true );
		Functions\expect( 'switch_to_blog' )->once()->with( 5 );
		Functions\expect( 'restore_current_blog' )->once();

		$site          = new \WP_Site();
		$site->blog_id = '5';
		$task          = new RecordingTask();
		$this->plugin()->task( $task )->lifecycle()->activateNewSite( $site );

		self::assertSame( array( 'activate' ), $task->calls );
	}

	public function test_new_site_is_skipped_when_not_network_active(): void {
		Functions\when( 'is_plugin_active_for_network' )->justReturn( false );
		Functions\expect( 'switch_to_blog' )->never();

		$task = new RecordingTask();
		$this->plugin()->task( $task )->lifecycle()->activateNewSite( new \WP_Site() );

		self::assertSame( array(), $task->calls );
	}
}

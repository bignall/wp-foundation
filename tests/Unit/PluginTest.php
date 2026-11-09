<?php
/**
 * Plugin tests.
 *
 * @package Arbe\WPFoundation
 */

declare(strict_types=1);

namespace Arbe\WPFoundation\Tests\Unit;

use Arbe\WPFoundation\Plugin;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;

final class PluginTest extends TestCase {

	public function test_derives_identity_from_file(): void {
		$plugin = $this->plugin();

		self::assertSame( 'acme-bookings/acme-bookings.php', $plugin->basename() );
		self::assertSame( 'acme-bookings', $plugin->slug() );
		self::assertSame( 'acme_bookings', $plugin->prefix() );
		self::assertSame( 'acme_bookings_version', $plugin->optionName( 'version' ) );
		self::assertSame( '/var/www/wp-content/plugins/acme-bookings/', $plugin->path() );
	}

	public function test_custom_prefix(): void {
		self::assertSame( 'acme', $this->plugin( 'acme' )->prefix() );
	}

	public function test_single_file_plugin_slug(): void {
		Functions\when( 'plugin_basename' )->justReturn( 'hello.php' );

		self::assertSame( 'hello', Plugin::create( '/plugins/hello.php' )->slug() );
	}

	public function test_version_from_header_and_override(): void {
		$this->headerVersion = '2.3.4';
		self::assertSame( '2.3.4', $this->plugin()->currentVersion() );
		self::assertSame( '9.0.0', $this->plugin()->version( '9.0.0' )->currentVersion() );
	}

	public function test_builtin_helpers_share_one_task_each(): void {
		$plugin = $this->plugin()
			->options( array( 'a' => 1 ) )
			->options( array( 'b' => 2 ) )
			->cron( 'acme_hourly', 'hourly' )
			->cron( 'acme_daily', 'daily' );

		self::assertCount( 2, $plugin->tasks() );
	}

	public function test_boot_registers_hooks_and_registry(): void {
		Functions\expect( 'register_activation_hook' )->once();
		Functions\expect( 'register_deactivation_hook' )->once();
		Functions\when( 'did_action' )->justReturn( 0 );

		$task   = new RecordingTask();
		$plugin = $this->plugin()->task( $task )->boot();
		$plugin->boot(); // Second call is a no-op.

		self::assertSame( $plugin, Plugin::instance( 'acme-bookings/acme-bookings.php' ) );
		self::assertSame( array( 'register' ), $task->calls );
		self::assertNotFalse( has_action( 'plugins_loaded', array( $plugin, 'load' ) ) );
	}

	public function test_load_runs_upgrade_then_ready_callbacks(): void {
		$order  = array();
		$plugin = $this->plugin()->onReady(
			function () use ( &$order ): void {
				$order[] = 'ready';
			}
		);
		Actions\expectDone( 'acme_bookings_ready' )->once();

		$plugin->load();

		self::assertTrue( $plugin->isReady() );
		self::assertSame( array( 'ready' ), $order );
		self::assertSame( '1.0.0', $this->options['acme_bookings_version'] );
	}

	public function test_load_does_not_start_when_requirements_fail(): void {
		$called = false;
		$plugin = $this->plugin()
			->requires( php: '99.0' )
			->onReady(
				function () use ( &$called ): void {
					$called = true;
				}
			);

		$plugin->load();

		self::assertFalse( $called );
		self::assertFalse( $plugin->isReady() );
		self::assertArrayNotHasKey( 'acme_bookings_version', $this->options );
		self::assertNotFalse( has_action( 'admin_notices' ) );
	}

	public function test_handle_uninstall_dispatches_to_plugin_from_action_name(): void {
		Functions\when( 'register_activation_hook' )->justReturn( null );
		Functions\when( 'register_deactivation_hook' )->justReturn( null );
		Functions\when( 'did_action' )->justReturn( 0 );
		Functions\when( 'current_action' )->justReturn( 'uninstall_acme-bookings/acme-bookings.php' );

		$task = new RecordingTask();
		$this->plugin()->task( $task )->deleteDataOnUninstall()->boot();
		$this->options['acme_bookings_version'] = '1.0.0';

		Plugin::handleUninstall();

		self::assertContains( 'uninstall', $task->calls );
		self::assertArrayNotHasKey( 'acme_bookings_version', $this->options );
	}

	public function test_delete_data_flag_can_be_a_callback_and_filtered(): void {
		$this->options['acme_remove_data'] = true;
		$plugin                            = $this->plugin()->deleteDataOnUninstall( fn (): bool => (bool) get_option( 'acme_remove_data' ) );
		self::assertTrue( $plugin->shouldDeleteDataOnUninstall() );

		\Brain\Monkey\Filters\expectApplied( 'acme_bookings_delete_data_on_uninstall' )->once()->andReturn( false );
		self::assertFalse( $plugin->shouldDeleteDataOnUninstall() );
	}
}

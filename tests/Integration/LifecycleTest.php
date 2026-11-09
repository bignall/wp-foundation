<?php
/**
 * End-to-end lifecycle against a real WordPress install.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

// phpcs:disable WordPress.DB -- Tests inspect the database directly.

namespace Bignall\WPFoundation\Tests\Integration;

use Bignall\WPFoundation\Feedback\DeactivationSurvey;
use Bignall\WPFoundation\Feedback\Handlers\LocalHandler;
use Bignall\WPFoundation\Tests\Integration\Fixtures\ItemsTable;

final class LifecycleTest extends IntegrationTestCase {

	public function test_activation_sets_everything_up(): void {
		$this->plugin()->lifecycle()->activate();

		self::assertTrue( ( new ItemsTable() )->exists() );
		self::assertSame( array( 'mode' => 'default' ), get_option( 'wpf_fixture_settings' ) );
		self::assertInstanceOf( \WP_Role::class, get_role( 'wpf_manager' ) );
		self::assertTrue( get_role( 'administrator' )->has_cap( 'wpf_manage_items' ) );
		self::assertSame( 'daily', wp_get_schedule( 'wpf_cleanup' ) );
		self::assertSame( '1.0.0', get_option( 'wpf_fixture_version' ) );
	}

	public function test_reactivation_keeps_existing_settings(): void {
		$plugin = $this->plugin();
		$plugin->lifecycle()->activate();
		update_option( 'wpf_fixture_settings', array( 'mode' => 'custom' ) );

		$plugin->lifecycle()->deactivate();
		$plugin->lifecycle()->activate();

		self::assertSame( array( 'mode' => 'custom' ), get_option( 'wpf_fixture_settings' ) );
	}

	public function test_deactivation_stops_cron_but_keeps_data(): void {
		$plugin = $this->plugin();
		$plugin->lifecycle()->activate();
		$plugin->lifecycle()->deactivate();

		self::assertFalse( wp_get_schedule( 'wpf_cleanup' ) );
		self::assertTrue( ( new ItemsTable() )->exists() );
		self::assertNotFalse( get_option( 'wpf_fixture_settings' ) );
		self::assertInstanceOf( \WP_Role::class, get_role( 'wpf_manager' ) );
	}

	public function test_upgrade_runs_migration_then_syncs_schema(): void {
		global $wpdb;
		$this->plugin()->lifecycle()->activate();
		$table = ( new ItemsTable() )->tableName();
		$wpdb->insert(
			$table,
			array(
				'title'      => 'first',
				'created_at' => '2026-01-01 00:00:00',
			)
		);

		$migrated = false;
		$this->plugin( '1.1.0', true )
			->migration(
				'1.1.0',
				static function () use ( $wpdb, $table, &$migrated ): void {
					$migrated = 1 === (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
				}
			)
			->lifecycle()
			->maybeUpgrade();

		self::assertTrue( $migrated );
		self::assertContains( 'notes', $this->columns( $table ) );
		self::assertSame( '1.1.0', get_option( 'wpf_fixture_version' ) );
		self::assertSame( 'first', $wpdb->get_var( "SELECT title FROM {$table}" ) );
	}

	public function test_uninstall_removes_everything(): void {
		$plugin = $this->plugin();
		$plugin->lifecycle()->activate();
		( new LocalHandler() )->handle(
			DeactivationSurvey::make()->buildFeedback( $plugin, array( 'reason' => 'temporary' ) ),
			$plugin
		);

		$plugin->lifecycle()->deactivate();
		$plugin->lifecycle()->uninstall();

		self::assertFalse( ( new ItemsTable() )->exists() );
		self::assertFalse( get_option( 'wpf_fixture_settings' ) );
		self::assertFalse( get_option( 'wpf_fixture_version' ) );
		self::assertFalse( get_option( 'wpf_fixture_deactivation_feedback' ) );
		self::assertNull( get_role( 'wpf_manager' ) );
		self::assertFalse( get_role( 'administrator' )->has_cap( 'wpf_manage_items' ) );
	}

	public function test_uninstall_keeps_data_when_disabled(): void {
		$plugin = $this->plugin()->deleteDataOnUninstall( false );
		$plugin->lifecycle()->activate();
		$plugin->lifecycle()->uninstall();

		self::assertTrue( ( new ItemsTable() )->exists() );
		self::assertSame( '1.0.0', get_option( 'wpf_fixture_version' ) );
	}

	public function test_activation_registers_uninstall_hook(): void {
		$plugin = $this->plugin();
		$plugin->lifecycle()->activate();

		$hooks = (array) get_option( 'uninstall_plugins' );
		self::assertSame( array( \Bignall\WPFoundation\Plugin::class, 'handleUninstall' ), $hooks[ $plugin->basename() ] ?? null );
	}
}

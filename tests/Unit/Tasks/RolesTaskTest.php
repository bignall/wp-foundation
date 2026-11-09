<?php
/**
 * Roles task tests.
 *
 * @package Arbe\WPFoundation
 */

declare(strict_types=1);

namespace Arbe\WPFoundation\Tests\Unit\Tasks;

use Arbe\WPFoundation\Tasks\RolesTask;
use Arbe\WPFoundation\Tests\Unit\TestCase;
use Brain\Monkey\Functions;
use WP_Role;

final class RolesTaskTest extends TestCase {

	/** @var array<string, WP_Role> */
	private array $roles = array();

	protected function setUp(): void {
		parent::setUp();
		$this->roles = array( 'administrator' => new WP_Role( 'administrator', array( 'manage_options' => true ) ) );

		Functions\when( 'get_role' )->alias( fn ( string $role ): ?WP_Role => $this->roles[ $role ] ?? null );
		Functions\when( 'add_role' )->alias(
			function ( string $role, string $name, array $caps ): WP_Role {
				$this->roles[ $role ] = new WP_Role( $role, $caps );
				return $this->roles[ $role ];
			}
		);
		Functions\when( 'remove_role' )->alias(
			function ( string $role ): void {
				unset( $this->roles[ $role ] );
			}
		);
	}

	private function task(): RolesTask {
		$task = new RolesTask();
		$task->role( 'acme_manager', 'Bookings Manager', array( 'read' => true ) );
		$task->grant( 'administrator', array( 'manage_bookings' ) );
		$task->grant( 'missing_role', array( 'manage_bookings' ) );
		return $task;
	}

	public function test_activate_creates_roles_and_grants_caps(): void {
		$this->task()->activate( $this->plugin() );

		self::assertArrayHasKey( 'acme_manager', $this->roles );
		self::assertTrue( $this->roles['administrator']->has_cap( 'manage_bookings' ) );
	}

	public function test_activate_adds_new_caps_to_existing_custom_role(): void {
		$this->roles['acme_manager'] = new WP_Role( 'acme_manager', array( 'read' => true ) );

		$task = new RolesTask();
		$task->role(
			'acme_manager',
			'Bookings Manager',
			array(
				'read'          => true,
				'edit_bookings' => true,
			)
		);
		$task->activate( $this->plugin() );

		self::assertTrue( $this->roles['acme_manager']->has_cap( 'edit_bookings' ) );
	}

	public function test_deactivate_keeps_roles_unless_configured(): void {
		$task = $this->task();
		$task->activate( $this->plugin() );
		$task->deactivate( $this->plugin() );
		self::assertArrayHasKey( 'acme_manager', $this->roles );

		$task->removeOnDeactivate();
		$task->deactivate( $this->plugin() );
		self::assertArrayNotHasKey( 'acme_manager', $this->roles );
		self::assertFalse( $this->roles['administrator']->has_cap( 'manage_bookings' ) );
	}

	public function test_uninstall_removes_everything(): void {
		$task = $this->task();
		$task->activate( $this->plugin() );
		$task->uninstall( $this->plugin() );

		self::assertArrayNotHasKey( 'acme_manager', $this->roles );
		self::assertFalse( $this->roles['administrator']->has_cap( 'manage_bookings' ) );
		self::assertTrue( $this->roles['administrator']->has_cap( 'manage_options' ) );
	}
}

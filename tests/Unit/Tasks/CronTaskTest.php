<?php
/**
 * Cron task tests.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Tests\Unit\Tasks;

use Bignall\WPFoundation\Tasks\CronTask;
use Bignall\WPFoundation\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

final class CronTaskTest extends TestCase {

	public function test_schedules_missing_events(): void {
		Functions\when( 'wp_get_schedule' )->justReturn( false );
		Functions\expect( 'wp_schedule_event' )->once()->with( \Mockery::type( 'int' ), 'hourly', 'acme_sync', array() );

		$task = new CronTask();
		$task->event( 'acme_sync', 'hourly' );
		$task->activate( $this->plugin() );
	}

	public function test_leaves_matching_events_alone(): void {
		Functions\when( 'wp_get_schedule' )->justReturn( 'hourly' );
		Functions\expect( 'wp_schedule_event' )->never();

		$task = new CronTask();
		$task->event( 'acme_sync', 'hourly' );
		$task->activate( $this->plugin() );
	}

	public function test_reschedules_when_recurrence_changes(): void {
		Functions\when( 'wp_get_schedule' )->justReturn( 'hourly' );
		Functions\expect( 'wp_clear_scheduled_hook' )->once()->with( 'acme_sync', array() );
		Functions\expect( 'wp_schedule_event' )->once()->with( \Mockery::type( 'int' ), 'daily', 'acme_sync', array() );

		$task = new CronTask();
		$task->event( 'acme_sync', 'daily' );
		$task->upgrade( $this->plugin(), '1.0.0', '1.1.0' );
	}

	public function test_deactivate_unschedules_each_hook_once(): void {
		Functions\expect( 'wp_unschedule_hook' )->once()->with( 'acme_sync' );

		$task = new CronTask();
		$task->event( 'acme_sync', 'hourly', array( 1 ) );
		$task->event( 'acme_sync', 'hourly', array( 2 ) );
		$task->deactivate( $this->plugin() );
	}

	public function test_registers_custom_schedules(): void {
		$task = new CronTask();
		$task->schedule( 'every_five_minutes', 300, 'Every five minutes' );
		$task->register( $this->plugin() );

		self::assertNotFalse( has_filter( 'cron_schedules', array( $task, 'addSchedules' ) ) );
		self::assertSame(
			array(
				'hourly'             => array(
					'interval' => 3600,
					'display'  => 'Hourly',
				),
				'every_five_minutes' => array(
					'interval' => 300,
					'display'  => 'Every five minutes',
				),
			),
			$task->addSchedules(
				array(
					'hourly' => array(
						'interval' => 3600,
						'display'  => 'Hourly',
					),
				)
			)
		);
	}
}

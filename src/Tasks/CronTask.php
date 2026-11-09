<?php
/**
 * Cron events.
 *
 * @package Arbe\WPFoundation
 */

declare(strict_types=1);

namespace Arbe\WPFoundation\Tasks;

use Arbe\WPFoundation\Plugin;

/**
 * Schedules recurring events while the plugin is active and registers custom schedules.
 */
final class CronTask extends AbstractTask {

	/**
	 * Events.
	 *
	 * @var list<array{hook: string, recurrence: string, args: list<mixed>}>
	 */
	private array $events = array();

	/**
	 * Custom schedules: name => [interval, display].
	 *
	 * @var array<string, array{interval: int, display: string}>
	 */
	private array $schedules = array();

	/**
	 * Add a recurring event.
	 *
	 * @param string      $hook       Action hook.
	 * @param string      $recurrence Schedule name.
	 * @param list<mixed> $args       Hook arguments.
	 */
	public function event( string $hook, string $recurrence, array $args = array() ): void {
		$this->events[] = array(
			'hook'       => $hook,
			'recurrence' => $recurrence,
			'args'       => $args,
		);
	}

	/**
	 * Add a custom schedule.
	 *
	 * @param string $name     Schedule name.
	 * @param int    $interval Seconds.
	 * @param string $display  Display name.
	 */
	public function schedule( string $name, int $interval, string $display ): void {
		$this->schedules[ $name ] = array(
			'interval' => $interval,
			'display'  => $display,
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function register( Plugin $plugin ): void {
		if ( array() !== $this->schedules ) {
			add_filter( 'cron_schedules', array( $this, 'addSchedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- Intervals are set by the plugin author.
		}
	}

	/**
	 * Add custom schedules.
	 *
	 * @param array<string, array{interval: int, display: string}> $schedules Schedules.
	 * @return array<string, array{interval: int, display: string}>
	 */
	public function addSchedules( $schedules ): array {
		return array_merge( (array) $schedules, $this->schedules );
	}

	/**
	 * Schedule missing events, and reschedule events whose recurrence changed.
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function activate( Plugin $plugin ): void {
		foreach ( $this->events as $event ) {
			$current = wp_get_schedule( $event['hook'], $event['args'] );

			if ( false !== $current && $current === $event['recurrence'] ) {
				continue;
			}
			if ( false !== $current ) {
				wp_clear_scheduled_hook( $event['hook'], $event['args'] );
			}

			wp_schedule_event( time(), $event['recurrence'], $event['hook'], $event['args'] );
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function deactivate( Plugin $plugin ): void {
		$this->unscheduleAll();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function uninstall( Plugin $plugin ): void {
		$this->unscheduleAll();
	}

	/**
	 * Remove every occurrence of every event, whatever its arguments.
	 */
	private function unscheduleAll(): void {
		foreach ( array_unique( array_column( $this->events, 'hook' ) ) as $hook ) {
			wp_unschedule_hook( $hook );
		}
	}
}

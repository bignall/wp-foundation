<?php
/**
 * Rewrite task tests.
 *
 * @package Arbe\WPFoundation
 */

declare(strict_types=1);

namespace Arbe\WPFoundation\Tests\Unit\Tasks;

use Arbe\WPFoundation\Tasks\RewriteTask;
use Arbe\WPFoundation\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

final class RewriteTaskTest extends TestCase {

	public function test_flushes_on_next_init_after_activation(): void {
		$plugin = $this->plugin();
		$task   = new RewriteTask();
		$task->activate( $plugin );
		self::assertSame( 1, $this->options['acme_bookings_flush_rewrite_rules'] );

		$callback = null;
		Functions\when( 'add_action' )->alias(
			function ( string $hook, callable $cb ) use ( &$callback ): void {
				$callback = $cb;
			}
		);
		$task->register( $plugin );

		Functions\expect( 'flush_rewrite_rules' )->once()->with( false );
		$callback();
		$callback(); // Flag is cleared, so no second flush.

		self::assertArrayNotHasKey( 'acme_bookings_flush_rewrite_rules', $this->options );
	}

	public function test_deactivate_deletes_stored_rules(): void {
		$this->options['rewrite_rules'] = array( 'x' => 'y' );

		( new RewriteTask() )->deactivate( $this->plugin() );

		self::assertArrayNotHasKey( 'rewrite_rules', $this->options );
	}
}

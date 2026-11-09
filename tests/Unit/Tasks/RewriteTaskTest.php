<?php
/**
 * Rewrite flushing tests.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Tests\Unit\Tasks;

use Bignall\WPFoundation\Tasks\RewriteTask;
use Bignall\WPFoundation\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

final class RewriteTaskTest extends TestCase {

	public function test_activation_requests_a_flush_that_runs_once_on_init(): void {
		$plugin = $this->plugin();
		( new RewriteTask() )->activate( $plugin );
		self::assertSame( 1, $this->options['acme_bookings_flush_rewrite_rules'] );

		Functions\expect( 'flush_rewrite_rules' )->once()->with( false );
		$plugin->lifecycle()->maybeFlushRewriteRules();
		$plugin->lifecycle()->maybeFlushRewriteRules(); // Flag is cleared, so no second flush.

		self::assertArrayNotHasKey( 'acme_bookings_flush_rewrite_rules', $this->options );
	}

	public function test_request_rewrite_flush_works_without_the_rewrite_task(): void {
		$plugin = $this->plugin();
		$plugin->requestRewriteFlush();

		Functions\expect( 'flush_rewrite_rules' )->once()->with( false );
		$plugin->lifecycle()->maybeFlushRewriteRules();
	}

	public function test_no_flush_without_a_request(): void {
		Functions\expect( 'flush_rewrite_rules' )->never();
		$this->plugin()->lifecycle()->maybeFlushRewriteRules();
	}

	public function test_flush_handler_is_registered_late_on_init_at_boot(): void {
		Functions\when( 'register_activation_hook' )->justReturn( null );
		Functions\when( 'register_deactivation_hook' )->justReturn( null );
		Functions\when( 'did_action' )->justReturn( 0 );

		$plugin = $this->plugin()->boot();

		self::assertSame( PHP_INT_MAX, has_action( 'init', array( $plugin->lifecycle(), 'maybeFlushRewriteRules' ) ) );
	}

	public function test_deactivate_deletes_stored_rules(): void {
		$this->options['rewrite_rules'] = array( 'x' => 'y' );

		( new RewriteTask() )->deactivate( $this->plugin() );

		self::assertArrayNotHasKey( 'rewrite_rules', $this->options );
	}
}

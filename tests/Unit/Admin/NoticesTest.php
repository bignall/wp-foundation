<?php
/**
 * Flash notice tests.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Tests\Unit\Admin;

use Bignall\WPFoundation\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

final class NoticesTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_current_user_id' )->justReturn( 7 );
	}

	public function test_flash_then_print_once(): void {
		$notices = $this->plugin()->notices();
		self::assertTrue( $notices->flash( 'success', 'Saved <b>now</b>' ) );
		self::assertTrue( $notices->flash( 'error', 'Some rows failed', array( 'Row 2', 'Row <5>' ) ) );
		self::assertArrayHasKey( 'acme_bookings_notices_7', $this->transients );

		ob_start();
		$notices->print();
		$html = (string) ob_get_clean();

		self::assertStringContainsString( '<div class="notice notice-success is-dismissible"><p>Saved &lt;b&gt;now&lt;/b&gt;</p></div>', $html );
		self::assertStringContainsString( '<li>Row &lt;5&gt;</li>', $html );
		self::assertArrayNotHasKey( 'acme_bookings_notices_7', $this->transients );

		ob_start();
		$notices->print();
		self::assertSame( '', ob_get_clean() );
	}

	public function test_unknown_type_falls_back_to_info(): void {
		$notices = $this->plugin()->notices();
		$notices->flash( 'shout', 'Hi' );

		self::assertSame( 'info', $notices->pending()[0]['type'] );
	}

	public function test_nothing_is_stored_without_a_user(): void {
		Functions\when( 'get_current_user_id' )->justReturn( 0 );

		self::assertFalse( $this->plugin()->notices()->flash( 'success', 'Saved' ) );
		self::assertSame( array(), $this->transients );
	}

	public function test_each_plugin_has_its_own_store(): void {
		$this->plugin()->notices()->flash( 'success', 'Mine' );

		self::assertSame( array(), $this->plugin( 'other_plugin' )->notices()->pending() );
	}

	public function test_printing_is_hooked_on_admin_requests_only(): void {
		Functions\when( 'register_activation_hook' )->justReturn( null );
		Functions\when( 'register_deactivation_hook' )->justReturn( null );
		Functions\when( 'did_action' )->justReturn( 0 );

		$front = $this->plugin()->boot();
		self::assertFalse( has_action( 'admin_notices', array( $front->notices(), 'print' ) ) );

		Functions\when( 'is_admin' )->justReturn( true );
		$admin = $this->plugin( 'admin_side' )->boot();
		self::assertNotFalse( has_action( 'admin_notices', array( $admin->notices(), 'print' ) ) );
	}
}

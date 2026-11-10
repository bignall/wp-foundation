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

	public function test_plain_text_items_never_become_links(): void {
		$notices = $this->plugin()->notices();
		$notices->flash( 'success', 'Created', array( '<a href="https://evil.test">x</a>' ) );

		ob_start();
		$notices->print();
		$html = (string) ob_get_clean();

		self::assertStringContainsString( '<li>&lt;a href=&quot;https://evil.test&quot;&gt;x&lt;/a&gt;</li>', $html );
		self::assertStringNotContainsString( '<a ', $html );
	}

	public function test_link_items_escape_text_and_url(): void {
		Functions\when( 'esc_url' )->alias(
			static function ( string $url ): string {
				// Like WordPress: disallowed protocols become '', the rest is HTML-escaped.
				return str_starts_with( $url, 'javascript:' ) ? '' : htmlspecialchars( $url, ENT_QUOTES );
			}
		);

		$notices = $this->plugin()->notices();
		$notices->flash(
			'success',
			'Link created',
			array(
				array(
					'text' => 'AI <Video> Maker',
					'url'  => 'https://example.test/wp-admin/post.php?post=5&action=edit"><script>',
				),
				array(
					'text' => 'Bad',
					'url'  => 'javascript:alert(1)',
				),
				array( 'text' => 'No URL' ),
				'Plain',
			)
		);

		self::assertSame(
			array(
				array(
					'text' => 'AI <Video> Maker',
					'url'  => 'https://example.test/wp-admin/post.php?post=5&action=edit"><script>',
				),
				array(
					'text' => 'Bad',
					'url'  => 'javascript:alert(1)',
				),
				array( 'text' => 'No URL' ),
				'Plain',
			),
			$notices->pending()[0]['items']
		);

		ob_start();
		$notices->print();
		$html = (string) ob_get_clean();

		self::assertStringContainsString( '<li><a href="https://example.test/wp-admin/post.php?post=5&amp;action=edit&quot;&gt;&lt;script&gt;">AI &lt;Video&gt; Maker</a></li>', $html );
		self::assertStringContainsString( '<li>Bad</li>', $html, 'A URL esc_url() rejects prints as plain text.' );
		self::assertStringContainsString( '<li>No URL</li>', $html );
		self::assertStringContainsString( '<li>Plain</li>', $html );
		self::assertStringNotContainsString( '<script>', $html );
		self::assertStringNotContainsString( 'javascript:', $html );
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

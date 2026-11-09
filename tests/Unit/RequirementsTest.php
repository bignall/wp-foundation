<?php
/**
 * Requirements tests.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Tests\Unit;

use Bignall\WPFoundation\Requirements\Requirements;
use Brain\Monkey\Functions;

final class RequirementsTest extends TestCase {

	public function test_no_requirements_pass(): void {
		self::assertSame( array(), ( new Requirements() )->errors() );
	}

	public function test_satisfied_requirements_pass(): void {
		Functions\when( 'is_plugin_active' )->justReturn( true );

		$errors = ( new Requirements() )
			->php( '8.1' )
			->wordpress( '6.4' )
			->extension( 'json' )
			->plugin( 'woocommerce/woocommerce.php', 'WooCommerce' )
			->custom( static fn (): ?string => null )
			->errors();

		self::assertSame( array(), $errors );
	}

	public function test_reports_every_unmet_requirement(): void {
		Functions\when( 'is_plugin_active' )->justReturn( false );

		$errors = ( new Requirements() )
			->php( '99.0' )
			->wordpress( '7.9' )
			->extension( 'definitely_not_loaded' )
			->plugin( 'woocommerce/woocommerce.php', 'WooCommerce' )
			->custom( static fn (): string => 'Custom failure' )
			->errors();

		self::assertCount( 5, $errors );
		self::assertStringContainsString( 'PHP 99.0', $errors[0] );
		self::assertStringContainsString( 'WordPress 7.9', $errors[1] );
		self::assertStringContainsString( 'definitely_not_loaded', $errors[2] );
		self::assertStringContainsString( 'WooCommerce', $errors[3] );
		self::assertSame( 'Custom failure', $errors[4] );
	}
}

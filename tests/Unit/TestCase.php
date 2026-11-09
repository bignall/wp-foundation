<?php
/**
 * Base unit test case.
 *
 * @package Arbe\WPFoundation
 */

declare(strict_types=1);

namespace Arbe\WPFoundation\Tests\Unit;

use Arbe\WPFoundation\Plugin;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Sets up Brain Monkey and an in-memory options table.
 */
abstract class TestCase extends PHPUnitTestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * In-memory options for the current site.
	 *
	 * @var array<string, mixed>
	 */
	protected array $options = array();

	protected string $pluginFile = '/var/www/wp-content/plugins/acme-bookings/acme-bookings.php';

	protected string $headerVersion = '1.0.0';

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Plugin::resetRegistry();
		$this->options = array();

		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();

		Functions\when( 'plugin_basename' )->alias(
			static fn ( string $file ): string => basename( dirname( $file ) ) . '/' . basename( $file )
		);
		Functions\when( 'get_file_data' )->alias( fn (): array => array( 'Version' => $this->headerVersion ) );
		Functions\when( 'trailingslashit' )->alias( static fn ( string $s ): string => rtrim( $s, '/\\' ) . '/' );
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'get_bloginfo' )->justReturn( '6.7' );

		Functions\when( 'get_option' )->alias(
			fn ( string $name, $default = false ) => array_key_exists( $name, $this->options ) ? $this->options[ $name ] : $default
		);
		Functions\when( 'add_option' )->alias(
			function ( string $name, $value = '' ): bool {
				if ( array_key_exists( $name, $this->options ) ) {
					return false;
				}
				$this->options[ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'update_option' )->alias(
			function ( string $name, $value ): bool {
				$this->options[ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			function ( string $name ): bool {
				$existed = array_key_exists( $name, $this->options );
				unset( $this->options[ $name ] );
				return $existed;
			}
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	protected function plugin( ?string $prefix = null ): Plugin {
		return Plugin::create( $this->pluginFile, $prefix );
	}
}

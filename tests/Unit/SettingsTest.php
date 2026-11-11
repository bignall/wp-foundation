<?php
/**
 * Settings tests.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Tests\Unit;

use Bignall\WPFoundation\Settings\Settings;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;

final class SettingsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_current_blog_id' )->justReturn( 1 );
	}

	private function settings( ?callable $sanitize = null ): Settings {
		return new Settings(
			'acme_settings',
			array(
				'mode'  => 'simple',
				'limit' => 10,
			),
			$sanitize
		);
	}

	public function test_defaults_until_saved(): void {
		$settings = $this->settings();
		self::assertSame(
			array(
				'mode'  => 'simple',
				'limit' => 10,
			),
			$settings->all()
		);
		self::assertSame( 10, $settings->get( 'limit' ) );
		self::assertNull( $settings->get( 'missing' ) );
		self::assertSame( 'x', $settings->get( 'missing', 'x' ) );
	}

	public function test_stored_values_win_and_new_defaults_fill_in(): void {
		$this->options['acme_settings'] = array( 'limit' => 25 );
		$settings                       = $this->settings();
		self::assertSame(
			array(
				'mode'  => 'simple',
				'limit' => 25,
			),
			$settings->all()
		);
	}

	public function test_non_array_stored_value_falls_back_to_defaults(): void {
		$this->options['acme_settings'] = 'corrupt';
		self::assertSame( 'simple', $this->settings()->get( 'mode' ) );
	}

	public function test_caches_until_flushed(): void {
		$settings = $this->settings();
		self::assertSame( 10, $settings->get( 'limit' ) );

		$this->options['acme_settings'] = array( 'limit' => 99 );
		self::assertSame( 10, $settings->get( 'limit' ), 'Cached for the request.' );

		$settings->flush();
		self::assertSame( 99, $settings->get( 'limit' ) );
	}

	public function test_update_keeps_other_values(): void {
		$settings = $this->settings();
		$settings->update( array( 'limit' => 50 ) );
		self::assertSame(
			array(
				'mode'  => 'simple',
				'limit' => 50,
			),
			$this->options['acme_settings']
		);
		self::assertSame( 50, $settings->get( 'limit' ) );
	}

	public function test_default_sanitize_keeps_known_keys_only(): void {
		$clean = $this->settings()->sanitize(
			array(
				'limit' => 5,
				'evil'  => 'x',
			)
		);
		self::assertSame(
			array(
				'mode'  => 'simple',
				'limit' => 5,
			),
			$clean
		);
		self::assertSame(
			array(
				'mode'  => 'simple',
				'limit' => 10,
			),
			$this->settings()->sanitize( 'not an array' )
		);
	}

	public function test_custom_sanitize_gets_input_and_settings(): void {
		$this->options['acme_settings'] = array( 'mode' => 'advanced' );
		$settings                       = $this->settings(
			static fn ( array $input, Settings $s ): array => array(
				'mode'  => $s->get( 'mode' ),
				'limit' => max( 1, (int) ( $input['limit'] ?? 0 ) ),
			)
		);
		self::assertSame(
			array(
				'mode'  => 'advanced',
				'limit' => 1,
			),
			$settings->sanitize( array( 'limit' => -4 ) )
		);
	}

	public function test_field_names(): void {
		$settings = $this->settings();
		self::assertSame( 'acme_settings[limit]', $settings->field( 'limit' ) );
		self::assertSame( 'acme_settings[sizes][]', $settings->field( 'sizes', '' ) );
		self::assertSame( 'acme_settings[placements][header][enabled]', $settings->field( 'placements', 'header', 'enabled' ) );
	}

	public function test_group_defaults_to_option_name(): void {
		self::assertSame( 'acme_settings', $this->settings()->group() );
		self::assertSame( 'acme-group', ( new Settings( 'acme_settings', array(), null, 'acme-group' ) )->group() );
	}

	public function test_register_hooks_cache_flushing(): void {
		$settings = $this->settings();
		Actions\expectAdded( 'admin_init' )->once();
		Actions\expectAdded( 'update_option_acme_settings' )->once()->with( array( $settings, 'flush' ) );
		$settings->register();
	}

	public function test_plugin_adds_defaults_on_activation_and_registers_on_boot(): void {
		$settings = $this->settings();
		$plugin   = $this->plugin()->settings( $settings );

		self::assertSame( $settings, $plugin->setting( 'acme_settings' ) );
		self::assertNull( $plugin->setting( 'other' ) );

		foreach ( $plugin->tasks() as $task ) {
			$task->activate( $plugin );
		}
		self::assertSame(
			array(
				'mode'  => 'simple',
				'limit' => 10,
			),
			$this->options['acme_settings']
		);
	}
}

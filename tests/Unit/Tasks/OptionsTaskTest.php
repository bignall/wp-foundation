<?php
/**
 * Options task tests.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Tests\Unit\Tasks;

use Bignall\WPFoundation\Tasks\OptionsTask;
use Bignall\WPFoundation\Tests\Unit\TestCase;

final class OptionsTaskTest extends TestCase {

	public function test_adds_defaults_without_overwriting_and_deletes_on_uninstall(): void {
		$this->options['acme_mode'] = 'custom';

		$task = new OptionsTask();
		$task->add(
			array(
				'acme_mode'  => 'default',
				'acme_limit' => 10,
			)
		);
		$task->activate( $this->plugin() );

		self::assertSame( 'custom', $this->options['acme_mode'] );
		self::assertSame( 10, $this->options['acme_limit'] );

		$task->uninstall( $this->plugin() );
		self::assertSame( array(), $this->options );
	}

	public function test_merge_adds_missing_keys_without_changing_existing_values(): void {
		$this->options['acme_settings'] = array(
			'mode'   => 'custom',
			'email'  => array( 'from' => 'me@example.com' ),
			'blocks' => array( 'a' ),
		);

		$task = new OptionsTask();
		$task->add(
			array(
				'acme_settings' => array(
					'mode'     => 'default',
					'url_base' => 'go',
					'email'    => array(
						'from'    => 'default@example.com',
						'subject' => 'Hello',
					),
					'blocks'   => array( 'a', 'b', 'c' ),
				),
			),
			merge: true
		);
		$task->upgrade( $this->plugin(), '1.0.0', '1.1.0' );

		self::assertSame(
			array(
				'mode'     => 'custom',
				'email'    => array(
					'from'    => 'me@example.com',
					'subject' => 'Hello',
				),
				'blocks'   => array( 'a' ),
				'url_base' => 'go',
			),
			$this->options['acme_settings']
		);
	}

	public function test_without_merge_existing_arrays_are_left_alone(): void {
		$this->options['acme_settings'] = array( 'mode' => 'custom' );

		$task = new OptionsTask();
		$task->add(
			array(
				'acme_settings' => array(
					'mode'     => 'default',
					'url_base' => 'go',
				),
			)
		);
		$task->upgrade( $this->plugin(), '1.0.0', '1.1.0' );

		self::assertSame( array( 'mode' => 'custom' ), $this->options['acme_settings'] );
	}

	public function test_merge_leaves_non_array_values_alone(): void {
		$this->options['acme_settings'] = 'legacy-string';

		$task = new OptionsTask();
		$task->add( array( 'acme_settings' => array( 'mode' => 'default' ) ), merge: true );
		$task->activate( $this->plugin() );

		self::assertSame( 'legacy-string', $this->options['acme_settings'] );
	}

	public function test_merge_through_plugin_builder(): void {
		$this->options['acme_settings']         = array( 'mode' => 'custom' );
		$this->options['acme_bookings_version'] = '1.0.0';
		$this->headerVersion                    = '1.1.0';

		$this->plugin()->options(
			array(
				'acme_settings' => array(
					'mode' => 'default',
					'new'  => true,
				),
			),
			merge: true
		)->lifecycle()->maybeUpgrade();

		self::assertSame(
			array(
				'mode' => 'custom',
				'new'  => true,
			),
			$this->options['acme_settings']
		);
	}

	public function test_fill_missing_handles_empty_stored_array(): void {
		self::assertSame( array( 'a' => 1 ), OptionsTask::fillMissing( array(), array( 'a' => 1 ) ) );
	}
}

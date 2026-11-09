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
}

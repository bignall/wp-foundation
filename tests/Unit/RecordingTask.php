<?php
/**
 * Task that records calls.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Tests\Unit;

use Bignall\WPFoundation\Plugin;
use Bignall\WPFoundation\Tasks\Task;

final class RecordingTask implements Task {

	/** @var list<string> */
	public array $calls = array();

	/**
	 * @param list<string> $log Shared log, to check ordering across tasks and migrations.
	 */
	public function __construct( private string $name = 'task', private ?\ArrayObject $log = null ) {}

	private function record( string $call ): void {
		$this->calls[] = $call;
		$this->log?->append( "{$this->name}:{$call}" );
	}

	public function register( Plugin $plugin ): void {
		$this->record( 'register' );
	}

	public function activate( Plugin $plugin ): void {
		$this->record( 'activate' );
	}

	public function deactivate( Plugin $plugin ): void {
		$this->record( 'deactivate' );
	}

	public function upgrade( Plugin $plugin, string $from, string $to ): void {
		$this->record( "upgrade {$from}->{$to}" );
	}

	public function uninstall( Plugin $plugin ): void {
		$this->record( 'uninstall' );
	}
}

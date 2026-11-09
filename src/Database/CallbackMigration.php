<?php
/**
 * Closure-based migration.
 *
 * @package Arbe\WPFoundation
 */

declare(strict_types=1);

namespace Arbe\WPFoundation\Database;

use Arbe\WPFoundation\Plugin;

/**
 * A migration defined by a callback.
 */
final class CallbackMigration implements Migration {

	/**
	 * Migration body.
	 *
	 * @var callable(Plugin): void
	 */
	private $up;

	/**
	 * Constructor.
	 *
	 * @param string                 $version Version that introduced the change.
	 * @param callable(Plugin): void $up      Migration body.
	 */
	public function __construct( private string $version, callable $up ) {
		$this->up = $up;
	}

	/**
	 * {@inheritDoc}
	 */
	public function version(): string {
		return $this->version;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function up( Plugin $plugin ): void {
		( $this->up )( $plugin );
	}
}

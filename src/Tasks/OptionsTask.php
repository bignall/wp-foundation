<?php
/**
 * Default options.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Tasks;

use Bignall\WPFoundation\Plugin;

/**
 * Adds default option values without overwriting existing ones, and deletes them on uninstall.
 */
final class OptionsTask extends AbstractTask {

	/**
	 * Options: name => [value, autoload].
	 *
	 * @var array<string, array{value: mixed, autoload: bool}>
	 */
	private array $options = array();

	/**
	 * Add options.
	 *
	 * @param array<string, mixed> $options  Option name => default value.
	 * @param bool                 $autoload Whether to autoload.
	 */
	public function add( array $options, bool $autoload = false ): void {
		foreach ( $options as $name => $value ) {
			$this->options[ $name ] = array(
				'value'    => $value,
				'autoload' => $autoload,
			);
		}
	}

	/**
	 * Option names.
	 *
	 * @return list<string>
	 */
	public function names(): array {
		return array_keys( $this->options );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function activate( Plugin $plugin ): void {
		foreach ( $this->options as $name => $option ) {
			// add_option() leaves existing values untouched. A boolean autoload works on WP 6.4+.
			add_option( $name, $option['value'], '', $option['autoload'] );
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function uninstall( Plugin $plugin ): void {
		foreach ( array_keys( $this->options ) as $name ) {
			delete_option( $name );
		}
	}
}

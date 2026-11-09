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
 *
 * Options added with `merge` also get any keys missing from a stored array filled in from the
 * defaults, so a key added in a later version reaches existing sites. Stored values always win.
 */
final class OptionsTask extends AbstractTask {

	/**
	 * Options: name => [value, autoload, merge].
	 *
	 * @var array<string, array{value: mixed, autoload: bool, merge: bool}>
	 */
	private array $options = array();

	/**
	 * Add options.
	 *
	 * @param array<string, mixed> $options  Option name => default value.
	 * @param bool                 $autoload Whether to autoload.
	 * @param bool                 $merge    Fill in keys missing from stored arrays.
	 */
	public function add( array $options, bool $autoload = false, bool $merge = false ): void {
		foreach ( $options as $name => $value ) {
			$this->options[ $name ] = array(
				'value'    => $value,
				'autoload' => $autoload,
				'merge'    => $merge,
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
			if ( add_option( $name, $option['value'], '', $option['autoload'] ) || ! $option['merge'] ) {
				continue;
			}

			$stored = get_option( $name );
			if ( ! is_array( $stored ) || ! is_array( $option['value'] ) ) {
				continue;
			}

			$merged = self::fillMissing( $stored, $option['value'] );
			if ( $merged !== $stored ) {
				update_option( $name, $merged );
			}
		}
	}

	/**
	 * Add keys from `$defaults` that `$stored` lacks, recursing into nested associative arrays.
	 * Existing values are kept as they are, and lists are never merged item by item.
	 *
	 * @param array<mixed> $stored   Stored value.
	 * @param array<mixed> $defaults Default value.
	 * @return array<mixed>
	 */
	public static function fillMissing( array $stored, array $defaults ): array {
		if ( array_is_list( $defaults ) || ( array() !== $stored && array_is_list( $stored ) ) ) {
			return $stored;
		}

		foreach ( $defaults as $key => $default ) {
			if ( ! array_key_exists( $key, $stored ) ) {
				$stored[ $key ] = $default;
			} elseif ( is_array( $stored[ $key ] ) && is_array( $default ) ) {
				$stored[ $key ] = self::fillMissing( $stored[ $key ], $default );
			}
		}

		return $stored;
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

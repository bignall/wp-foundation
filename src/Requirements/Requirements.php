<?php
/**
 * Environment requirements.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Requirements;

/**
 * Checks PHP and WordPress versions, PHP extensions, required plugins and custom conditions.
 */
final class Requirements {

	private ?string $php       = null;
	private ?string $wordpress = null;

	/**
	 * Required PHP extensions.
	 *
	 * @var list<string>
	 */
	private array $extensions = array();

	/**
	 * Required plugins: basename => display name.
	 *
	 * @var array<string, string>
	 */
	private array $plugins = array();

	/**
	 * Custom checks returning an error message or null.
	 *
	 * @var list<callable(): ?string>
	 */
	private array $custom = array();

	/**
	 * Require a minimum PHP version.
	 *
	 * @param string|null $version Version, or null for no requirement.
	 */
	public function php( ?string $version ): self {
		$this->php = $version;
		return $this;
	}

	/**
	 * Require a minimum WordPress version.
	 *
	 * @param string|null $version Version, or null for no requirement.
	 */
	public function wordpress( ?string $version ): self {
		$this->wordpress = $version;
		return $this;
	}

	/**
	 * Require a PHP extension.
	 *
	 * @param string $extension Extension name as reported by `extension_loaded()`.
	 */
	public function extension( string $extension ): self {
		$this->extensions[] = $extension;
		return $this;
	}

	/**
	 * Require another plugin to be active.
	 *
	 * @param string $basename Plugin basename, e.g. `woocommerce/woocommerce.php`.
	 * @param string $name     Display name.
	 */
	public function plugin( string $basename, string $name ): self {
		$this->plugins[ $basename ] = $name;
		return $this;
	}

	/**
	 * Add a custom check.
	 *
	 * @param callable(): ?string $check Returns an error message, or null when satisfied.
	 */
	public function custom( callable $check ): self {
		$this->custom[] = $check;
		return $this;
	}

	/**
	 * Run all checks.
	 *
	 * @return list<string> Error messages; empty when every requirement is met.
	 */
	public function errors(): array {
		$errors = array();

		if ( null !== $this->php && version_compare( PHP_VERSION, $this->php, '<' ) ) {
			$errors[] = sprintf(
				/* translators: 1: required PHP version, 2: current PHP version. */
				__( 'PHP %1$s or newer is required. This site runs PHP %2$s.', 'wp-foundation' ),
				$this->php,
				PHP_VERSION
			);
		}

		if ( null !== $this->wordpress ) {
			$wpVersion = (string) get_bloginfo( 'version' );
			if ( version_compare( $wpVersion, $this->wordpress, '<' ) ) {
				$errors[] = sprintf(
					/* translators: 1: required WordPress version, 2: current WordPress version. */
					__( 'WordPress %1$s or newer is required. This site runs WordPress %2$s.', 'wp-foundation' ),
					$this->wordpress,
					$wpVersion
				);
			}
		}

		foreach ( $this->extensions as $extension ) {
			if ( ! extension_loaded( $extension ) ) {
				$errors[] = sprintf(
					/* translators: %s: PHP extension name. */
					__( 'The PHP extension "%s" is required.', 'wp-foundation' ),
					$extension
				);
			}
		}

		if ( array() !== $this->plugins ) {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			foreach ( $this->plugins as $basename => $name ) {
				if ( ! is_plugin_active( $basename ) ) {
					$errors[] = sprintf(
						/* translators: %s: plugin name. */
						__( 'The plugin "%s" must be installed and active.', 'wp-foundation' ),
						$name
					);
				}
			}
		}

		foreach ( $this->custom as $check ) {
			$error = $check();
			if ( null !== $error && '' !== $error ) {
				$errors[] = $error;
			}
		}

		return $errors;
	}
}

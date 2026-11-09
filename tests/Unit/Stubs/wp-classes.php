<?php
/**
 * Minimal stand-ins for WordPress classes used in unit tests.
 *
 * @package Bignall\WPFoundation
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound, PEAR.NamingConventions.ValidClassName

if ( ! class_exists( 'WP_Role' ) ) {
	class WP_Role {
		/** @var array<string, bool> */
		public array $capabilities;

		public function __construct( public string $name, array $capabilities = array() ) {
			$this->capabilities = $capabilities;
		}

		public function add_cap( string $cap, bool $grant = true ): void {
			$this->capabilities[ $cap ] = $grant;
		}

		public function remove_cap( string $cap ): void {
			unset( $this->capabilities[ $cap ] );
		}

		public function has_cap( string $cap ): bool {
			return ! empty( $this->capabilities[ $cap ] );
		}
	}
}

if ( ! class_exists( 'WP_Site' ) ) {
	class WP_Site {
		public string $blog_id = '1';
	}
}

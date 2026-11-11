<?php
/**
 * A plugin's settings, stored as one array option.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Settings;

/**
 * Reads, sanitizes and registers an array option with defaults.
 *
 * Most plugins keep their settings in one option and write the same code to use it: merge the
 * stored value over defaults, cache it for the request, read one key, register it with the
 * Settings API with a sanitize callback, and build `name="option[key]"` field names. This class
 * is that code.
 *
 *     $settings = new Settings( 'acme_settings', [ 'mode' => 'simple', 'limit' => 10 ], $sanitize );
 *     Plugin::create( __FILE__ )->settings( $settings );
 *
 *     $settings->get( 'limit' );   // 10 until saved otherwise.
 *
 * Passed to `Plugin::settings()`, the defaults are added on activation and merged into the stored
 * value on upgrade (new keys reach existing sites), the option is registered with the Settings
 * API, and it is deleted on uninstall when data deletion is enabled.
 */
final class Settings {

	/**
	 * Request cache, per site: blog ID => merged values.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private array $cache = array();

	/**
	 * Sanitize callback.
	 *
	 * @var (callable(array<string, mixed>, Settings): array<string, mixed>)|null
	 */
	private $sanitize;

	/**
	 * Constructor.
	 *
	 * @param string                                                                $option   Option name.
	 * @param array<string, mixed>                                                  $defaults Default values.
	 * @param (callable(array<string, mixed>, Settings): array<string, mixed>)|null $sanitize Cleans submitted values. It receives the
	 *                                                                                  submitted array (never anything else) and this object, so it
	 *                                                                                  can fall back to the current values with `get()`. Without one,
	 *                                                                                  only keys present in the defaults are kept.
	 * @param string                                                                $group    Settings API group, used by `settings_fields()`. Defaults to the option name.
	 * @param bool                                                                  $autoload Whether to autoload the option.
	 */
	public function __construct(
		private string $option,
		private array $defaults,
		?callable $sanitize = null,
		private string $group = '',
		private bool $autoload = true
	) {
		$this->sanitize = $sanitize;
		if ( '' === $this->group ) {
			$this->group = $this->option;
		}
	}

	/**
	 * Option name.
	 */
	public function name(): string {
		return $this->option;
	}

	/**
	 * Settings API group, for `settings_fields()`.
	 */
	public function group(): string {
		return $this->group;
	}

	/**
	 * Whether the option is autoloaded.
	 */
	public function autoload(): bool {
		return $this->autoload;
	}

	/**
	 * Default values.
	 *
	 * @return array<string, mixed>
	 */
	public function defaults(): array {
		return $this->defaults;
	}

	/**
	 * All values: the stored ones merged over the defaults (top-level keys).
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$site = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 0;
		if ( ! isset( $this->cache[ $site ] ) ) {
			$stored               = get_option( $this->option, array() );
			$this->cache[ $site ] = array_merge( $this->defaults, is_array( $stored ) ? $stored : array() );
		}
		return $this->cache[ $site ];
	}

	/**
	 * One value.
	 *
	 * @param string $key     Key.
	 * @param mixed  $fallback Returned when the key is neither stored nor in the defaults.
	 */
	public function get( string $key, mixed $fallback = null ): mixed {
		$all = $this->all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Change some values, keeping the others. Runs the sanitize callback like a form submission.
	 *
	 * @param array<string, mixed> $values Key => value.
	 */
	public function update( array $values ): bool {
		$result = update_option( $this->option, array_merge( $this->all(), $values ) );
		$this->flush();
		return $result;
	}

	/**
	 * Forget cached values, e.g. after writing the option directly.
	 */
	public function flush(): void {
		$this->cache = array();
	}

	/**
	 * The `name` attribute for a field, e.g. `acme_settings[limit]` or `acme_settings[sizes][]`.
	 *
	 * @param string $key    Key.
	 * @param string ...$sub Nested keys; pass '' for a list (`[]`).
	 */
	public function field( string $key, string ...$sub ): string {
		$name = $this->option . '[' . $key . ']';
		foreach ( $sub as $part ) {
			$name .= '[' . $part . ']';
		}
		return $name;
	}

	/**
	 * Hook into WordPress: register the setting on `admin_init` (and `rest_api_init`), and keep
	 * the cache fresh when the option changes.
	 *
	 * Called by `Plugin::boot()` for settings passed to `Plugin::settings()`.
	 */
	public function register(): void {
		add_action( 'admin_init', array( $this, 'registerSetting' ) );
		add_action( 'rest_api_init', array( $this, 'registerSetting' ) );
		add_action( 'add_option_' . $this->option, array( $this, 'flush' ) );
		add_action( 'update_option_' . $this->option, array( $this, 'flush' ) );
		add_action( 'delete_option_' . $this->option, array( $this, 'flush' ) );
	}

	/**
	 * Register the option with the Settings API.
	 *
	 * @internal Hooked to `admin_init` and `rest_api_init`.
	 */
	public function registerSetting(): void {
		register_setting(
			$this->group,
			$this->option,
			array(
				'type'              => 'array',
				'default'           => $this->defaults,
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	/**
	 * Clean a submitted value.
	 *
	 * @internal The Settings API calls this; call `update()` to save values from code.
	 *
	 * @param mixed $input Submitted value.
	 * @return array<string, mixed>
	 */
	public function sanitize( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();
		if ( null !== $this->sanitize ) {
			return ( $this->sanitize )( $input, $this );
		}
		return array_merge( $this->defaults, array_intersect_key( $input, $this->defaults ) );
	}
}

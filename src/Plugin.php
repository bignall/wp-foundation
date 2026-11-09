<?php
/**
 * Plugin definition and entry point.
 *
 * @package Arbe\WPFoundation
 */

declare(strict_types=1);

namespace Arbe\WPFoundation;

use Arbe\WPFoundation\Database\CallbackMigration;
use Arbe\WPFoundation\Database\Migration;
use Arbe\WPFoundation\Database\Table;
use Arbe\WPFoundation\Feedback\DeactivationSurvey;
use Arbe\WPFoundation\Lifecycle\Lifecycle;
use Arbe\WPFoundation\Requirements\Requirements;
use Arbe\WPFoundation\Tasks\CronTask;
use Arbe\WPFoundation\Tasks\OptionsTask;
use Arbe\WPFoundation\Tasks\RewriteTask;
use Arbe\WPFoundation\Tasks\RolesTask;
use Arbe\WPFoundation\Tasks\TablesTask;
use Arbe\WPFoundation\Tasks\Task;

/**
 * Describes a WordPress plugin and wires its lifecycle into WordPress.
 *
 * Typical usage from the plugin's main file:
 *
 *     Plugin::create( __FILE__ )
 *         ->requires( php: '8.1', wp: '6.4' )
 *         ->options( [ 'acme_settings' => [] ] )
 *         ->tables( new BookingsTable() )
 *         ->deactivationSurvey()
 *         ->onReady( fn () => ( new Acme\App() )->run() )
 *         ->boot();
 */
final class Plugin {

	/**
	 * Booted plugins, keyed by plugin basename.
	 *
	 * @var array<string, Plugin>
	 */
	private static array $registry = array();

	private string $basename;
	private string $slug;
	private string $prefix;
	private ?string $version = null;
	private Requirements $requirements;
	private Lifecycle $lifecycle;
	private ?DeactivationSurvey $survey = null;

	/**
	 * Tasks in registration order, keyed by a unique id.
	 *
	 * @var array<string, Task>
	 */
	private array $tasks = array();

	/**
	 * Migrations to run on upgrade.
	 *
	 * @var list<Migration>
	 */
	private array $migrations = array();

	/**
	 * Whether uninstalling deletes the plugin's data. A callable is evaluated at uninstall time.
	 *
	 * @var bool|callable(Plugin): bool
	 */
	private $deleteDataOnUninstall = false;

	/**
	 * Callbacks run on `plugins_loaded` once requirements pass and upgrades have run.
	 *
	 * @var list<callable(Plugin): void>
	 */
	private array $readyCallbacks = array();

	private bool $booted = false;
	private bool $ready  = false;

	/**
	 * Constructor.
	 *
	 * @param string      $file   Absolute path to the plugin's main file.
	 * @param string|null $prefix Prefix for options and hooks. Defaults to the slug with underscores.
	 */
	private function __construct( private string $file, ?string $prefix = null ) {
		$this->basename     = plugin_basename( $file );
		$this->slug         = self::slugFromBasename( $this->basename );
		$this->prefix       = $prefix ?? self::prefixFromSlug( $this->slug );
		$this->requirements = new Requirements();
		$this->lifecycle    = new Lifecycle( $this );
	}

	/**
	 * Start describing a plugin.
	 *
	 * @param string      $file   Absolute path to the plugin's main file (usually `__FILE__`).
	 * @param string|null $prefix Prefix for options and hooks. Defaults to the slug with underscores.
	 */
	public static function create( string $file, ?string $prefix = null ): self {
		return new self( $file, $prefix );
	}

	/**
	 * Look up a booted plugin by its basename (e.g. `acme-bookings/acme-bookings.php`).
	 *
	 * @param string $basename Plugin basename.
	 */
	public static function instance( string $basename ): ?self {
		return self::$registry[ $basename ] ?? null;
	}

	/**
	 * Uninstall callback registered with `register_uninstall_hook()`.
	 *
	 * WordPress stores this callable in the database, so it must be static. The plugin
	 * is identified from the current action name, `uninstall_{basename}`.
	 */
	public static function handleUninstall(): void {
		$action   = (string) current_action();
		$basename = str_starts_with( $action, 'uninstall_' ) ? substr( $action, strlen( 'uninstall_' ) ) : '';
		$plugin   = self::instance( $basename );

		$plugin?->lifecycle()->uninstall();
	}

	/**
	 * Forget all booted plugins. Intended for tests.
	 *
	 * @internal
	 */
	public static function resetRegistry(): void {
		self::$registry = array();
	}

	// ---------------------------------------------------------------------
	// Configuration.
	// ---------------------------------------------------------------------

	/**
	 * Declare minimum environment requirements. Unmet requirements block activation and,
	 * at runtime, stop `onReady()` callbacks from running and show an admin notice.
	 *
	 * @param string|null           $php        Minimum PHP version.
	 * @param string|null           $wp         Minimum WordPress version.
	 * @param list<string>          $extensions Required PHP extensions.
	 * @param array<string, string> $plugins    Required plugins: basename => display name.
	 */
	public function requires( ?string $php = null, ?string $wp = null, array $extensions = array(), array $plugins = array() ): self {
		$this->requirements->php( $php )->wordpress( $wp );

		foreach ( $extensions as $extension ) {
			$this->requirements->extension( $extension );
		}
		foreach ( $plugins as $basename => $name ) {
			$this->requirements->plugin( $basename, $name );
		}

		return $this;
	}

	/**
	 * Add a custom requirement. The callback returns an error message, or null when satisfied.
	 *
	 * @param callable(): ?string $check Requirement check.
	 */
	public function requirement( callable $check ): self {
		$this->requirements->custom( $check );
		return $this;
	}

	/**
	 * Override the version read from the plugin header.
	 *
	 * @param string $version Plugin version.
	 */
	public function version( string $version ): self {
		$this->version = $version;
		return $this;
	}

	/**
	 * Add default options on activation and upgrade (existing values are never overwritten).
	 * They are deleted on uninstall when data deletion is enabled.
	 *
	 * @param array<string, mixed> $options  Option name => default value.
	 * @param bool                 $autoload Whether to autoload these options.
	 */
	public function options( array $options, bool $autoload = false ): self {
		$this->sharedTask( OptionsTask::class )->add( $options, $autoload );
		return $this;
	}

	/**
	 * Grant capabilities to existing roles on activation.
	 *
	 * @param array<string, list<string>> $capabilities Role name => capabilities.
	 * @param bool                        $removeOnDeactivate Also remove them on deactivation, not only on uninstall.
	 */
	public function capabilities( array $capabilities, bool $removeOnDeactivate = false ): self {
		$task = $this->sharedTask( RolesTask::class );
		foreach ( $capabilities as $role => $caps ) {
			$task->grant( $role, $caps );
		}
		if ( $removeOnDeactivate ) {
			$task->removeOnDeactivate();
		}
		return $this;
	}

	/**
	 * Create a custom role on activation.
	 *
	 * @param string              $role         Role name.
	 * @param string              $displayName  Human-readable name.
	 * @param array<string, bool> $capabilities Capabilities.
	 */
	public function role( string $role, string $displayName, array $capabilities = array( 'read' => true ) ): self {
		$this->sharedTask( RolesTask::class )->role( $role, $displayName, $capabilities );
		return $this;
	}

	/**
	 * Schedule a recurring cron event while the plugin is active.
	 *
	 * @param string      $hook       Action hook to run.
	 * @param string      $recurrence Schedule name (hourly, twicedaily, daily, weekly or a custom one).
	 * @param list<mixed> $args       Arguments passed to the hook.
	 */
	public function cron( string $hook, string $recurrence, array $args = array() ): self {
		$this->sharedTask( CronTask::class )->event( $hook, $recurrence, $args );
		return $this;
	}

	/**
	 * Register a custom cron schedule.
	 *
	 * @param string $name     Schedule name.
	 * @param int    $interval Interval in seconds.
	 * @param string $display  Human-readable name.
	 */
	public function cronSchedule( string $name, int $interval, string $display ): self {
		$this->sharedTask( CronTask::class )->schedule( $name, $interval, $display );
		return $this;
	}

	/**
	 * Create or update custom database tables with dbDelta on activation and upgrade,
	 * and drop them on uninstall when data deletion is enabled.
	 *
	 * @param Table ...$tables Tables.
	 */
	public function tables( Table ...$tables ): self {
		$task = $this->sharedTask( TablesTask::class );
		foreach ( $tables as $table ) {
			$task->add( $table );
		}
		return $this;
	}

	/**
	 * Add versioned migrations, run in version order when the plugin is upgraded.
	 *
	 * @param Migration ...$migrations Migrations.
	 */
	public function migrations( Migration ...$migrations ): self {
		array_push( $this->migrations, ...$migrations );
		return $this;
	}

	/**
	 * Add a migration from a callback.
	 *
	 * @param string                 $version Version that introduces the change.
	 * @param callable(Plugin): void $up      Migration body.
	 */
	public function migration( string $version, callable $up ): self {
		$this->migrations[] = new CallbackMigration( $version, $up );
		return $this;
	}

	/**
	 * Flush rewrite rules after activation and upgrade (on the next `init`, once post types
	 * are registered) and drop the plugin's rules on deactivation.
	 */
	public function flushRewriteRules(): self {
		$this->sharedTask( RewriteTask::class );
		return $this;
	}

	/**
	 * Add a custom lifecycle task.
	 *
	 * @param Task $task Task.
	 */
	public function task( Task $task ): self {
		$this->tasks[ spl_object_hash( $task ) ] = $task;
		return $this;
	}

	/**
	 * Ask why the plugin is being deactivated, on the Plugins screen.
	 *
	 * @param DeactivationSurvey|null $survey Survey configuration. Defaults to the standard reasons
	 *                                        with responses stored on the site.
	 */
	public function deactivationSurvey( ?DeactivationSurvey $survey = null ): self {
		$this->survey = $survey ?? DeactivationSurvey::make();
		return $this;
	}

	/**
	 * Choose whether uninstalling deletes the plugin's data (options, tables, roles, ...).
	 *
	 * @param bool|callable(Plugin): bool $delete A boolean, or a callback evaluated at uninstall time,
	 *                                            for example one that reads a "remove data" setting.
	 */
	public function deleteDataOnUninstall( bool|callable $delete = true ): self {
		$this->deleteDataOnUninstall = $delete;
		return $this;
	}

	/**
	 * Run a callback once requirements are met and any upgrade has run. This is where the
	 * plugin's own code should start.
	 *
	 * @param callable(Plugin): void $callback Callback.
	 */
	public function onReady( callable $callback ): self {
		$this->readyCallbacks[] = $callback;
		return $this;
	}

	/**
	 * Register everything with WordPress. Call once, from the plugin's main file.
	 */
	public function boot(): self {
		if ( $this->booted ) {
			return $this;
		}
		$this->booted                      = true;
		self::$registry[ $this->basename ] = $this;

		$this->lifecycle->register();

		foreach ( $this->tasks as $task ) {
			$task->register( $this );
		}

		$this->survey?->register( $this );

		if ( did_action( 'plugins_loaded' ) ) {
			$this->load();
		} else {
			add_action( 'plugins_loaded', array( $this, 'load' ), 5 );
		}

		return $this;
	}

	/**
	 * Check requirements, run pending upgrades and start the plugin.
	 *
	 * @internal Hooked to `plugins_loaded`.
	 */
	public function load(): void {
		$errors = $this->requirements->errors();
		if ( array() !== $errors ) {
			$this->lifecycle->showRequirementErrors( $errors );
			return;
		}

		$this->lifecycle->maybeUpgrade();

		$this->ready = true;
		foreach ( $this->readyCallbacks as $callback ) {
			$callback( $this );
		}

		/**
		 * Fires once the plugin's requirements are met and upgrades have run.
		 *
		 * @param Plugin $plugin The plugin.
		 */
		do_action( "{$this->prefix}_ready", $this );
	}

	// ---------------------------------------------------------------------
	// Accessors.
	// ---------------------------------------------------------------------

	/**
	 * Absolute path to the plugin's main file.
	 */
	public function file(): string {
		return $this->file;
	}

	/**
	 * Absolute path to the plugin's directory, with a trailing slash.
	 */
	public function path(): string {
		return trailingslashit( dirname( $this->file ) );
	}

	/**
	 * Plugin basename, e.g. `acme-bookings/acme-bookings.php`.
	 */
	public function basename(): string {
		return $this->basename;
	}

	/**
	 * Plugin slug, e.g. `acme-bookings`.
	 */
	public function slug(): string {
		return $this->slug;
	}

	/**
	 * Prefix used for the framework's options and hooks, e.g. `acme_bookings`.
	 */
	public function prefix(): string {
		return $this->prefix;
	}

	/**
	 * Name of an option owned by the framework for this plugin, e.g. `acme_bookings_version`.
	 *
	 * @param string $key Option key.
	 */
	public function optionName( string $key ): string {
		return "{$this->prefix}_{$key}";
	}

	/**
	 * Current plugin version, from `version()` or the plugin header.
	 */
	public function currentVersion(): string {
		if ( null === $this->version ) {
			$headers       = get_file_data( $this->file, array( 'Version' => 'Version' ), 'plugin' );
			$this->version = '' !== $headers['Version'] ? $headers['Version'] : '0.0.0';
		}
		return $this->version;
	}

	/**
	 * Whether requirements passed and `onReady()` callbacks have run.
	 */
	public function isReady(): bool {
		return $this->ready;
	}

	/**
	 * Requirements.
	 */
	public function requirements(): Requirements {
		return $this->requirements;
	}

	/**
	 * Lifecycle manager.
	 */
	public function lifecycle(): Lifecycle {
		return $this->lifecycle;
	}

	/**
	 * Deactivation survey, if enabled.
	 */
	public function survey(): ?DeactivationSurvey {
		return $this->survey;
	}

	/**
	 * Tasks in registration order.
	 *
	 * @return list<Task>
	 */
	public function tasks(): array {
		return array_values( $this->tasks );
	}

	/**
	 * Migrations, unsorted.
	 *
	 * @return list<Migration>
	 */
	public function migrationList(): array {
		return $this->migrations;
	}

	/**
	 * Whether uninstalling should delete data.
	 */
	public function shouldDeleteDataOnUninstall(): bool {
		$delete = $this->deleteDataOnUninstall;
		$result = is_callable( $delete ) ? (bool) $delete( $this ) : $delete;

		/**
		 * Filters whether uninstalling the plugin deletes its data.
		 *
		 * @param bool   $result Whether to delete data.
		 * @param Plugin $plugin The plugin.
		 */
		return (bool) apply_filters( "{$this->prefix}_delete_data_on_uninstall", $result, $this );
	}

	// ---------------------------------------------------------------------
	// Internals.
	// ---------------------------------------------------------------------

	/**
	 * Get or create the single instance of a built-in task.
	 *
	 * @template T of Task
	 * @param class-string<T> $class Task class.
	 * @return T
	 */
	private function sharedTask( string $class ): Task {
		if ( ! isset( $this->tasks[ $class ] ) ) {
			$this->tasks[ $class ] = new $class();
		}
		/** @var T */
		return $this->tasks[ $class ];
	}

	/**
	 * Derive a slug from a basename: the directory name, or the file name for single-file plugins.
	 *
	 * @param string $basename Plugin basename.
	 */
	private static function slugFromBasename( string $basename ): string {
		$dir = dirname( $basename );
		return '.' === $dir ? basename( $basename, '.php' ) : $dir;
	}

	/**
	 * Turn a slug into an option/hook prefix.
	 *
	 * @param string $slug Plugin slug.
	 */
	private static function prefixFromSlug( string $slug ): string {
		return trim( (string) preg_replace( '/[^a-z0-9]+/', '_', strtolower( $slug ) ), '_' );
	}
}

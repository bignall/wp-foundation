<?php
/**
 * Deactivation survey.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Feedback;

use Bignall\WPFoundation\Feedback\Handlers\LocalHandler;
use Bignall\WPFoundation\Plugin;
use Throwable;

/**
 * Asks why the plugin is being deactivated when a user clicks "Deactivate" on the Plugins
 * screen. The survey is always optional: users can skip it, and deactivation continues even
 * if sending the response fails.
 *
 *     DeactivationSurvey::make()
 *         ->addReason( new Reason( 'too_expensive', __( 'It costs too much', 'acme' ) ) )
 *         ->withHandler( new RemoteHandler( 'https://example.com/feedback' ) );
 */
final class DeactivationSurvey {

	private const MAX_DETAILS_LENGTH = 1000;

	/**
	 * Reasons, keyed by id.
	 *
	 * @var array<string, Reason>
	 */
	private array $reasons = array();

	/**
	 * Handlers.
	 *
	 * @var list<FeedbackHandler>
	 */
	private array $handlers = array();

	private ?string $title = null;

	/**
	 * A survey with the default reasons that stores responses on the site.
	 */
	public static function make(): self {
		$survey = new self();
		foreach ( self::defaultReasons() as $reason ) {
			$survey->addReason( $reason );
		}
		return $survey->withHandler( new LocalHandler() );
	}

	/**
	 * The default reasons.
	 *
	 * @return list<Reason>
	 */
	public static function defaultReasons(): array {
		return array(
			new Reason( 'temporary', __( "It's a temporary deactivation, I'm troubleshooting", 'wp-foundation' ) ),
			new Reason( 'found_better', __( 'I found a better plugin', 'wp-foundation' ), __( 'Which plugin?', 'wp-foundation' ) ),
			new Reason( 'no_longer_needed', __( 'I no longer need the plugin', 'wp-foundation' ) ),
			new Reason( 'not_working', __( "It isn't working or broke my site", 'wp-foundation' ), __( 'What went wrong?', 'wp-foundation' ) ),
			new Reason( 'missing_feature', __( "It's missing a feature I need", 'wp-foundation' ), __( 'Which feature?', 'wp-foundation' ) ),
			new Reason( 'too_complicated', __( "It's too hard to set up or use", 'wp-foundation' ), __( 'What was confusing?', 'wp-foundation' ) ),
			new Reason( 'other', __( 'Other', 'wp-foundation' ), __( 'Please tell us more', 'wp-foundation' ) ),
		);
	}

	/**
	 * Add a reason, or replace one with the same id.
	 *
	 * @param Reason $reason Reason.
	 */
	public function addReason( Reason $reason ): self {
		$this->reasons[ $reason->id ] = $reason;
		return $this;
	}

	/**
	 * Remove a reason.
	 *
	 * @param string $id Reason id.
	 */
	public function removeReason( string $id ): self {
		unset( $this->reasons[ $id ] );
		return $this;
	}

	/**
	 * Replace all reasons.
	 *
	 * @param list<Reason> $reasons Reasons.
	 */
	public function withReasons( array $reasons ): self {
		$this->reasons = array();
		foreach ( $reasons as $reason ) {
			$this->addReason( $reason );
		}
		return $this;
	}

	/**
	 * Add a handler.
	 *
	 * @param FeedbackHandler $handler Handler.
	 */
	public function withHandler( FeedbackHandler $handler ): self {
		$this->handlers[] = $handler;
		return $this;
	}

	/**
	 * Stop storing responses on the site.
	 */
	public function withoutLocalStorage(): self {
		$this->handlers = array_values( array_filter( $this->handlers, static fn ( FeedbackHandler $h ): bool => ! $h instanceof LocalHandler ) );
		return $this;
	}

	/**
	 * Set the dialog title.
	 *
	 * @param string $title Title.
	 */
	public function withTitle( string $title ): self {
		$this->title = $title;
		return $this;
	}

	/**
	 * Reasons after filtering.
	 *
	 * @param Plugin $plugin The plugin.
	 * @return list<Reason>
	 */
	public function reasons( Plugin $plugin ): array {
		/**
		 * Filters the deactivation reasons.
		 *
		 * @param list<Reason> $reasons Reasons.
		 * @param Plugin       $plugin  The plugin.
		 */
		$reasons = apply_filters( "{$plugin->prefix()}_deactivation_reasons", array_values( $this->reasons ), $plugin );

		// Other code can return anything from the filter, so keep only valid reasons.
		return array_values(
			array_filter(
				(array) $reasons,
				// @phpstan-ignore instanceof.alwaysTrue
				static fn ( $r ): bool => $r instanceof Reason
			)
		);
	}

	/**
	 * Handlers.
	 *
	 * @return list<FeedbackHandler>
	 */
	public function handlers(): array {
		return $this->handlers;
	}

	/**
	 * Whether any handler sends data off the site.
	 */
	public function sendsDataOffSite(): bool {
		foreach ( $this->handlers as $handler ) {
			if ( $handler->isRemote() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * AJAX action name.
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function ajaxAction( Plugin $plugin ): string {
		return "{$plugin->prefix()}_deactivation_feedback";
	}

	/**
	 * Register admin hooks.
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function register( Plugin $plugin ): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action(
			'admin_enqueue_scripts',
			function ( string $hookSuffix ) use ( $plugin ): void {
				if ( 'plugins.php' === $hookSuffix ) {
					$this->enqueue( $plugin );
				}
			}
		);

		add_action(
			'wp_ajax_' . $this->ajaxAction( $plugin ),
			function () use ( $plugin ): void {
				$this->handleAjax( $plugin );
			}
		);
	}

	/**
	 * Add the survey script and styles to the Plugins screen.
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function enqueue( Plugin $plugin ): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		/**
		 * Filters whether to show the deactivation survey.
		 *
		 * @param bool   $show   Whether to show it.
		 * @param Plugin $plugin The plugin.
		 */
		if ( ! apply_filters( "{$plugin->prefix()}_show_deactivation_survey", true, $plugin ) ) {
			return;
		}

		$handle = "{$plugin->slug()}-deactivation-survey";
		$script = $this->readAsset( self::assetPath( 'deactivation-survey.js' ) );
		if ( '' === $script ) {
			// The asset is missing, e.g. a build left it out. Skip the survey; deactivation still works.
			return;
		}

		// Assets are inlined so they work wherever the library is installed or prefixed.
		wp_register_script( $handle, false, array(), $plugin->currentVersion(), true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NotInFooter
		wp_enqueue_script( $handle );
		wp_add_inline_script( $handle, $script . '(' . wp_json_encode( $this->scriptConfig( $plugin ) ) . ');' );

		wp_register_style( $handle, false, array(), $plugin->currentVersion() );
		wp_enqueue_style( $handle );
		wp_add_inline_style( $handle, $this->readAsset( self::assetPath( 'deactivation-survey.css' ) ) );
	}

	/**
	 * Absolute path to a survey asset. Assets live under src/ so tools that copy a package's
	 * autoloaded directories, such as Strauss, include them.
	 *
	 * @param string $file File name.
	 */
	public static function assetPath( string $file ): string {
		return __DIR__ . '/assets/' . $file;
	}

	/**
	 * Configuration passed to the survey script.
	 *
	 * @param Plugin $plugin The plugin.
	 * @return array<string, mixed>
	 */
	public function scriptConfig( Plugin $plugin ): array {
		$remote = $this->sendsDataOffSite();

		return array(
			'basename'   => $plugin->basename(),
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'action'     => $this->ajaxAction( $plugin ),
			'nonce'      => wp_create_nonce( $this->ajaxAction( $plugin ) ),
			'reasons'    => array_map( static fn ( Reason $r ): array => $r->toArray(), $this->reasons( $plugin ) ),
			'askConsent' => $remote,
			'maxLength'  => self::MAX_DETAILS_LENGTH,
			'strings'    => array(
				'title'    => $this->title ?? __( 'Quick question before you deactivate', 'wp-foundation' ),
				'intro'    => __( 'If you have a moment, please tell us why. It helps us improve.', 'wp-foundation' ),
				'privacy'  => $remote
					? __( 'Your answer, plugin and WordPress versions are sent to the plugin developer. No personal data is included.', 'wp-foundation' )
					: '',
				'consent'  => __( 'Include my site address so the developer can follow up', 'wp-foundation' ),
				'submit'   => __( 'Submit & deactivate', 'wp-foundation' ),
				'skip'     => __( 'Skip & deactivate', 'wp-foundation' ),
				'cancel'   => __( 'Cancel', 'wp-foundation' ),
				'required' => __( 'Please choose a reason, or skip.', 'wp-foundation' ),
			),
		);
	}

	/**
	 * Validate submitted data and build a Feedback object.
	 *
	 * @param Plugin               $plugin The plugin.
	 * @param array<string, mixed> $input  Unslashed request data.
	 * @return Feedback|null Null when the reason is missing or unknown.
	 */
	public function buildFeedback( Plugin $plugin, array $input ): ?Feedback {
		$reasonId = isset( $input['reason'] ) ? sanitize_key( (string) $input['reason'] ) : '';
		$valid    = array_map( static fn ( Reason $r ): string => $r->id, $this->reasons( $plugin ) );
		if ( ! in_array( $reasonId, $valid, true ) ) {
			return null;
		}

		$details = isset( $input['details'] ) ? sanitize_textarea_field( (string) $input['details'] ) : '';
		$details = mb_substr( $details, 0, self::MAX_DETAILS_LENGTH );
		$consent = $this->sendsDataOffSite() && ! empty( $input['consent'] ) && 'false' !== $input['consent'];

		return new Feedback(
			reason: $reasonId,
			details: $details,
			plugin: $plugin->slug(),
			pluginVersion: $plugin->currentVersion(),
			wpVersion: (string) get_bloginfo( 'version' ),
			phpVersion: PHP_VERSION,
			locale: get_locale(),
			multisite: is_multisite(),
			submittedAt: time(),
			siteUrl: $consent ? home_url() : null,
		);
	}

	/**
	 * Pass a response to every handler.
	 *
	 * @param Feedback $feedback Response.
	 * @param Plugin   $plugin   The plugin.
	 */
	public function dispatch( Feedback $feedback, Plugin $plugin ): void {
		foreach ( $this->handlers as $handler ) {
			try {
				$handler->handle( $feedback, $plugin );
			} catch ( Throwable $e ) {
				/**
				 * Fires when a feedback handler throws.
				 *
				 * @param Throwable       $e       The error.
				 * @param FeedbackHandler $handler The handler.
				 * @param Plugin          $plugin  The plugin.
				 */
				do_action( "{$plugin->prefix()}_deactivation_feedback_failed", $e, $handler, $plugin );
			}
		}

		/**
		 * Fires after a deactivation survey response is handled.
		 *
		 * @param Feedback $feedback Response.
		 * @param Plugin   $plugin   The plugin.
		 */
		do_action( "{$plugin->prefix()}_deactivation_feedback", $feedback, $plugin );
	}

	/**
	 * AJAX endpoint.
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function handleAjax( Plugin $plugin ): void {
		check_ajax_referer( $this->ajaxAction( $plugin ), 'nonce' );

		if ( ! current_user_can( 'activate_plugins' ) ) {
			wp_send_json_error( null, 403 );
		}

		$feedback = $this->buildFeedback( $plugin, wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in buildFeedback().
		if ( null === $feedback ) {
			wp_send_json_error( null, 400 );
		}

		$this->dispatch( $feedback, $plugin );
		wp_send_json_success();
	}

	/**
	 * Read a bundled asset.
	 *
	 * @param string $path Absolute path.
	 */
	private function readAsset( string $path ): string {
		$contents = is_readable( $path ) ? file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		return false === $contents ? '' : $contents;
	}
}

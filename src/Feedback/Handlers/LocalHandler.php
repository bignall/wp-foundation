<?php
/**
 * Stores feedback on the site.
 *
 * @package Arbe\WPFoundation
 */

declare(strict_types=1);

namespace Arbe\WPFoundation\Feedback\Handlers;

use Arbe\WPFoundation\Feedback\Feedback;
use Arbe\WPFoundation\Feedback\FeedbackHandler;
use Arbe\WPFoundation\Plugin;

/**
 * Keeps the most recent responses in a non-autoloaded option, `{prefix}_deactivation_feedback`.
 * Read them with `LocalHandler::entries( $plugin )`, for example after reactivation.
 */
final class LocalHandler implements FeedbackHandler {

	/**
	 * Constructor.
	 *
	 * @param int $limit Number of responses to keep.
	 */
	public function __construct( private int $limit = 50 ) {}

	/**
	 * {@inheritDoc}
	 *
	 * @param Feedback $feedback Response.
	 * @param Plugin   $plugin   The plugin.
	 */
	public function handle( Feedback $feedback, Plugin $plugin ): void {
		$entries   = self::entries( $plugin );
		$entries[] = $feedback->toArray();
		$entries   = array_slice( $entries, -$this->limit );

		update_option( $plugin->optionName( 'deactivation_feedback' ), $entries, false );
	}

	/**
	 * {@inheritDoc}
	 */
	public function isRemote(): bool {
		return false;
	}

	/**
	 * Stored responses, oldest first.
	 *
	 * @param Plugin $plugin The plugin.
	 * @return list<array<string, mixed>>
	 */
	public static function entries( Plugin $plugin ): array {
		$entries = get_option( $plugin->optionName( 'deactivation_feedback' ), array() );
		return is_array( $entries ) ? array_values( $entries ) : array();
	}
}

<?php
/**
 * Feedback handler contract.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Feedback;

use Bignall\WPFoundation\Plugin;

/**
 * Receives deactivation feedback. Implement this to send responses to your own service.
 */
interface FeedbackHandler {

	/**
	 * Handle a response. Exceptions are caught and never block deactivation.
	 *
	 * @param Feedback $feedback Response.
	 * @param Plugin   $plugin   The plugin.
	 */
	public function handle( Feedback $feedback, Plugin $plugin ): void;

	/**
	 * Whether this handler sends data off the site. If any handler does, the survey tells the
	 * user and asks before including their site URL.
	 */
	public function isRemote(): bool;
}

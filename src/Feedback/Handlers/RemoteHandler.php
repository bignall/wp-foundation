<?php
/**
 * Sends feedback to a URL.
 *
 * @package Arbe\WPFoundation
 */

declare(strict_types=1);

namespace Arbe\WPFoundation\Feedback\Handlers;

use Arbe\WPFoundation\Feedback\Feedback;
use Arbe\WPFoundation\Feedback\FeedbackHandler;
use Arbe\WPFoundation\Plugin;

/**
 * POSTs each response as JSON to an endpoint you control. Non-blocking by default, so a slow
 * endpoint never delays deactivation.
 */
final class RemoteHandler implements FeedbackHandler {

	/**
	 * Constructor.
	 *
	 * @param string                $url      Endpoint URL (HTTPS recommended).
	 * @param array<string, string> $headers  Extra request headers, e.g. an API key.
	 * @param bool                  $blocking Wait for the response.
	 * @param int                   $timeout  Timeout in seconds.
	 */
	public function __construct(
		private string $url,
		private array $headers = array(),
		private bool $blocking = false,
		private int $timeout = 5,
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @param Feedback $feedback Response.
	 * @param Plugin   $plugin   The plugin.
	 */
	public function handle( Feedback $feedback, Plugin $plugin ): void {
		$args = array(
			'body'     => (string) wp_json_encode( $feedback->toArray() ),
			'headers'  => array_merge( array( 'Content-Type' => 'application/json' ), $this->headers ),
			'blocking' => $this->blocking,
			'timeout'  => $this->timeout,
		);

		/**
		 * Filters the request sent for a deactivation survey response.
		 *
		 * @param array<string, mixed> $args     Arguments for wp_remote_post().
		 * @param Feedback             $feedback Response.
		 * @param Plugin               $plugin   The plugin.
		 */
		$args = (array) apply_filters( "{$plugin->prefix()}_deactivation_feedback_request", $args, $feedback, $plugin );

		wp_remote_post( $this->url, $args );
	}

	/**
	 * {@inheritDoc}
	 */
	public function isRemote(): bool {
		return true;
	}
}

<?php
/**
 * Plugin definition.
 *
 * @package AcmeBookings
 */

declare(strict_types=1);

namespace AcmeBookings;

use Arbe\WPFoundation\Feedback\DeactivationSurvey;
use Arbe\WPFoundation\Feedback\Handlers\RemoteHandler;
use Arbe\WPFoundation\Feedback\Reason;
use Arbe\WPFoundation\Plugin;

/**
 * Describes Acme Bookings' lifecycle.
 */
final class Bootstrap {

	/**
	 * Build the plugin definition.
	 *
	 * @param string $file Main plugin file.
	 */
	public static function plugin( string $file ): Plugin {
		return Plugin::create( $file, 'acme_bookings' )
			->requires(
				php: '8.1',
				wp: '6.4',
				extensions: array( 'json' ),
			)
			->options(
				array(
					'acme_bookings_settings' => array(
						'slot_minutes'      => 30,
						'remove_data'       => false,
						'notification_mail' => '',
					),
				)
			)
			->role(
				'acme_booking_manager',
				'Booking Manager',
				array(
					'read'                 => true,
					'manage_acme_bookings' => true,
				)
			)
			->capabilities( array( 'administrator' => array( 'manage_acme_bookings' ) ) )
			->cronSchedule( 'acme_every_15_minutes', 15 * MINUTE_IN_SECONDS, 'Every 15 minutes' )
			->cron( 'acme_bookings_send_reminders', 'acme_every_15_minutes' )
			->cron( 'acme_bookings_purge_expired', 'daily' )
			->tables( new BookingsTable() )
			->migration( '1.1.0', array( Migrations::class, 'backfillStatus' ) )
			->flushRewriteRules()
			->deactivationSurvey(
				DeactivationSurvey::make()
					->addReason( new Reason( 'too_expensive', __( 'The premium version costs too much', 'acme-bookings' ) ) )
				// Uncomment to also send responses to your own endpoint:
				// ->withHandler( new RemoteHandler( 'https://example.com/api/deactivation-feedback' ) ) .
			)
			// Only delete bookings on uninstall if the site owner opted in.
			->deleteDataOnUninstall( static fn (): bool => ! empty( get_option( 'acme_bookings_settings' )['remove_data'] ) )
			->onReady( static fn ( Plugin $plugin ) => ( new App( $plugin ) )->run() );
	}

	/**
	 * Deactivation survey: the default reasons plus one of our own. Responses are stored on the
	 * site; set ACME_BOOKINGS_FEEDBACK_URL to also send them to your own endpoint.
	 */
	private static function survey(): DeactivationSurvey {
		$survey = DeactivationSurvey::make()
			->addReason( new Reason( 'too_expensive', __( 'The premium version costs too much', 'acme-bookings' ) ) );

		if ( defined( 'ACME_BOOKINGS_FEEDBACK_URL' ) ) {
			$survey->withHandler( new RemoteHandler( (string) ACME_BOOKINGS_FEEDBACK_URL ) );
		}

		return $survey;
	}
}

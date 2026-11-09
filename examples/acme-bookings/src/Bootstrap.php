<?php
/**
 * Plugin definition.
 *
 * @package AcmeBookings
 */

declare(strict_types=1);

namespace AcmeBookings;

use Bignall\WPFoundation\Feedback\DeactivationSurvey;
use Bignall\WPFoundation\Feedback\Handlers\RemoteHandler;
use Bignall\WPFoundation\Feedback\Reason;
use Bignall\WPFoundation\Plugin;

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
			// Before adopting WP Foundation, Acme Bookings 1.0 kept its schema version in its own
			// option. Reading it means those sites get the 1.1.0 migration instead of a fresh install.
			->legacyVersion( 'acme_bookings_db_version' )
			// merge: settings keys added in later versions (services_slug in 1.1.0) are filled in
			// on existing sites, without touching values the site owner has changed.
			->options(
				array(
					'acme_bookings_settings' => array(
						'slot_minutes'      => 30,
						'remove_data'       => false,
						'notification_mail' => '',
						'services_slug'     => 'services',
					),
				),
				merge: true
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
			// On uninstall (when remove_data is on), delete every service post and service type term.
			->postTypes( App::POST_TYPE )
			->taxonomies( App::TAXONOMY )
			->deactivationSurvey( self::survey() )
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

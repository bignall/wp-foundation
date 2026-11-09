<?php
/**
 * Plugin runtime.
 *
 * @package AcmeBookings
 */

declare(strict_types=1);

namespace AcmeBookings;

use Bignall\WPFoundation\Plugin;

/**
 * The plugin's own features. Runs only once requirements pass and upgrades are done.
 */
final class App {

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Register hooks.
	 */
	public function run(): void {
		add_action( 'init', array( $this, 'registerPostType' ) );
		add_action( 'acme_bookings_send_reminders', array( $this, 'sendReminders' ) );
		add_action( 'acme_bookings_purge_expired', array( $this, 'purgeExpired' ) );
	}

	/**
	 * Public booking pages; their rewrite rules are flushed by the framework after activation.
	 */
	public function registerPostType(): void {
		register_post_type(
			'acme_service',
			array(
				'label'        => __( 'Services', 'acme-bookings' ),
				'public'       => true,
				'show_in_rest' => true,
				'rewrite'      => array( 'slug' => 'services' ),
				'capabilities' => array( 'edit_posts' => 'manage_acme_bookings' ),
			)
		);
	}

	/**
	 * Cron: send reminders for upcoming bookings.
	 */
	public function sendReminders(): void {
		// Application code goes here.
	}

	/**
	 * Cron: delete cancelled bookings older than a year.
	 */
	public function purgeExpired(): void {
		global $wpdb;
		$table = ( new BookingsTable() )->tableName();
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE status = 'cancelled' AND ends_at < %s", gmdate( 'Y-m-d H:i:s', time() - YEAR_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}

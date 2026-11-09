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

	public const POST_TYPE = 'acme_service';
	public const TAXONOMY  = 'acme_service_type';

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
		add_action( 'update_option_acme_bookings_settings', array( $this, 'settingsChanged' ), 10, 2 );
		add_action( 'admin_post_acme_bookings_purge', array( $this, 'handlePurgeRequest' ) );
		add_action( 'acme_bookings_send_reminders', array( $this, 'sendReminders' ) );
		add_action( 'acme_bookings_purge_expired', array( $this, 'purgeExpired' ) );
	}

	/**
	 * Public booking pages; their rewrite rules are flushed by the framework after activation.
	 */
	public function registerPostType(): void {
		$settings = (array) get_option( 'acme_bookings_settings', array() );

		register_post_type(
			self::POST_TYPE,
			array(
				'label'        => __( 'Services', 'acme-bookings' ),
				'public'       => true,
				'show_in_rest' => true,
				'rewrite'      => array( 'slug' => $settings['services_slug'] ?? 'services' ),
				'capabilities' => array( 'edit_posts' => 'manage_acme_bookings' ),
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'label'        => __( 'Service types', 'acme-bookings' ),
				'hierarchical' => true,
				'show_in_rest' => true,
			)
		);
	}

	/**
	 * Rebuild rewrite rules when the services URL changes. The flush runs at the end of the next
	 * `init`, after the post type has been registered with the new slug.
	 *
	 * @param mixed $old Previous settings.
	 * @param mixed $new New settings.
	 */
	public function settingsChanged( $old, $new ): void {
		$oldSlug = ( (array) $old )['services_slug'] ?? null;
		$newSlug = ( (array) $new )['services_slug'] ?? null;

		if ( $oldSlug !== $newSlug ) {
			$this->plugin->requestRewriteFlush();
		}
	}

	/**
	 * Admin form handler: purge expired bookings now, then redirect back with a notice
	 * (post/redirect/get).
	 */
	public function handlePurgeRequest(): void {
		if ( ! current_user_can( 'manage_acme_bookings' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'acme-bookings' ), 403 );
		}
		check_admin_referer( 'acme_bookings_purge' );

		$deleted = $this->purgeExpired();

		$this->plugin->notices()->flash(
			'success',
			sprintf(
				/* translators: %d: number of bookings. */
				_n( 'Purged %d expired booking.', 'Purged %d expired bookings.', $deleted, 'acme-bookings' ),
				$deleted
			)
		);

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
		exit;
	}

	/**
	 * Cron: send reminders for upcoming bookings.
	 */
	public function sendReminders(): void {
		// Application code goes here.
	}

	/**
	 * Cron and admin action: delete cancelled bookings older than a year.
	 *
	 * @return int Number of bookings deleted.
	 */
	public function purgeExpired(): int {
		global $wpdb;
		$table = ( new BookingsTable() )->tableName();
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE status = 'cancelled' AND ends_at < %s", gmdate( 'Y-m-d H:i:s', time() - YEAR_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}

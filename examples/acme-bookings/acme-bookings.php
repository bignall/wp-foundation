<?php
/**
 * Plugin Name:       Acme Bookings
 * Description:       Example plugin built on Arbe WP Foundation.
 * Version:           1.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Arbe
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       acme-bookings
 *
 * @package AcmeBookings
 */

defined( 'ABSPATH' ) || exit;

// Bail out before loading any PHP 8.1 code on older PHP versions. This file must stay
// PHP 5.6-compatible syntax-wise so the check itself can run.
if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Acme Bookings requires PHP 8.1 or newer.', 'acme-bookings' ) . '</p></div>';
		}
	);
	return;
}

// With Strauss (see composer.json), the prefixed library is autoloaded from vendor-prefixed/.
if ( file_exists( __DIR__ . '/vendor-prefixed/autoload.php' ) ) {
	require_once __DIR__ . '/vendor-prefixed/autoload.php';
}
require_once __DIR__ . '/vendor/autoload.php';

AcmeBookings\Bootstrap::plugin( __FILE__ )->boot();

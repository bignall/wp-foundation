<?php
/**
 * Bookings table.
 *
 * @package AcmeBookings
 */

declare(strict_types=1);

namespace AcmeBookings;

use Bignall\WPFoundation\Database\Table;

/**
 * Stores bookings. The `status` column was added in 1.1.0; dbDelta adds it on upgrade.
 */
final class BookingsTable extends Table {

	/**
	 * {@inheritDoc}
	 */
	public function name(): string {
		return 'acme_bookings';
	}

	/**
	 * {@inheritDoc}
	 */
	public function columns(): string {
		return "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
user_id bigint(20) unsigned NOT NULL DEFAULT 0,
starts_at datetime NOT NULL,
ends_at datetime NOT NULL,
status varchar(20) NOT NULL DEFAULT 'confirmed',
created_at datetime NOT NULL,
PRIMARY KEY  (id),
KEY user_id (user_id),
KEY starts_at (starts_at)";
	}
}

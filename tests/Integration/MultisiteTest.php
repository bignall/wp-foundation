<?php
/**
 * Multisite behaviour. Run with WP_MULTISITE=1.
 *
 * @package Arbe\WPFoundation
 */

declare(strict_types=1);

// phpcs:disable WordPress.DB -- Tests inspect the database directly.

namespace Arbe\WPFoundation\Tests\Integration;

use Arbe\WPFoundation\Tests\Integration\Fixtures\ItemsTable;

/**
 * @group multisite
 */
final class MultisiteTest extends IntegrationTestCase {

	public function set_up(): void {
		parent::set_up();
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Multisite only. Run with WP_MULTISITE=1.' );
		}
	}

	private function tableExistsOn( int $siteId ): bool {
		switch_to_blog( $siteId );
		$exists = ( new ItemsTable() )->exists();
		restore_current_blog();
		return $exists;
	}

	public function test_network_activation_and_uninstall_cover_every_site(): void {
		$siteId = self::factory()->blog->create();
		$plugin = $this->plugin();

		$plugin->lifecycle()->activate( true );
		self::assertTrue( $this->tableExistsOn( get_main_site_id() ) );
		self::assertTrue( $this->tableExistsOn( $siteId ) );

		$plugin->lifecycle()->uninstall();
		self::assertFalse( $this->tableExistsOn( get_main_site_id() ) );
		self::assertFalse( $this->tableExistsOn( $siteId ) );
	}

	public function test_new_site_gets_installed_and_cleaned_up(): void {
		$plugin = $this->plugin()->boot();
		update_site_option( 'active_sitewide_plugins', array( $plugin->basename() => time() ) );

		$siteId = self::factory()->blog->create();
		self::assertTrue( $this->tableExistsOn( $siteId ) );

		$table = ( new ItemsTable() )->tableNameForSite( $siteId );
		wp_delete_site( $siteId );

		global $wpdb;
		self::assertNull( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) );
	}
}

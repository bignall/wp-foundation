<?php
/**
 * Legacy installs, option merging, content cleanup and rewrite flush requests in real WordPress.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

// phpcs:disable WordPress.DB -- Tests inspect the database directly.

namespace Bignall\WPFoundation\Tests\Integration;

use Bignall\WPFoundation\Plugin;
use Bignall\WPFoundation\Tests\Integration\Fixtures\ItemsTable;

final class AdoptionAndCleanupTest extends IntegrationTestCase {

	public function tear_down(): void {
		delete_option( 'wpf_fixture_old_db_version' );
		delete_option( 'wpf_fixture_settings' );
		delete_option( 'wpf_fixture_version' );
		delete_option( 'wpf_fixture_installed_at' );
		unregister_post_type( 'wpf_item' );
		if ( taxonomy_exists( 'wpf_item_cat' ) ) {
			unregister_taxonomy( 'wpf_item_cat' );
		}
		parent::tear_down();
	}

	public function test_plugin_that_predates_the_framework_runs_its_migrations(): void {
		// v0.1.0 shipped without the framework and created its table and its own version option.
		( new ItemsTable() )->install();
		update_option( 'wpf_fixture_old_db_version', '0.1.0' );

		$migrated = false;
		Plugin::create( self::FILE, 'wpf_fixture' )
			->version( '0.2.0' )
			->tables( new ItemsTable( true ) )
			->legacyVersion( 'wpf_fixture_old_db_version' )
			->migration(
				'0.2.0',
				static function () use ( &$migrated ): void {
					$migrated = true;
				}
			)
			->lifecycle()
			->maybeUpgrade();

		self::assertTrue( $migrated );
		self::assertSame( '0.2.0', get_option( 'wpf_fixture_version' ) );
		self::assertContains( 'notes', $this->columns( ( new ItemsTable() )->tableName() ), 'Schema synced on the upgrade path.' );
	}

	public function test_new_default_keys_reach_existing_sites(): void {
		update_option( 'wpf_fixture_settings', array( 'mode' => 'custom' ) );
		update_option( 'wpf_fixture_version', '1.0.0' );

		Plugin::create( self::FILE, 'wpf_fixture' )
			->version( '1.1.0' )
			->options(
				array(
					'wpf_fixture_settings' => array(
						'mode'     => 'default',
						'url_base' => 'go',
					),
				),
				merge: true
			)
			->lifecycle()
			->maybeUpgrade();

		self::assertSame(
			array(
				'mode'     => 'custom',
				'url_base' => 'go',
			),
			get_option( 'wpf_fixture_settings' )
		);
	}

	public function test_uninstall_deletes_posts_in_every_status_and_terms(): void {
		register_post_type( 'wpf_item' );
		register_taxonomy( 'wpf_item_cat', 'wpf_item' );

		$ids = array();
		foreach ( array( 'publish', 'draft', 'auto-draft', 'trash', 'private' ) as $status ) {
			$ids[] = self::factory()->post->create(
				array(
					'post_type'   => 'wpf_item',
					'post_status' => $status,
				)
			);
		}
		add_post_meta( $ids[0], 'wpf_meta', 'x' );
		$other  = self::factory()->post->create();
		$termId = self::factory()->term->create( array( 'taxonomy' => 'wpf_item_cat' ) );
		add_term_meta( $termId, 'wpf_term_meta', 'y' );
		wp_set_object_terms( $ids[0], array( $termId ), 'wpf_item_cat' );

		// As during a real uninstall: the plugin's init code hasn't run.
		unregister_taxonomy( 'wpf_item_cat' );

		Plugin::create( self::FILE, 'wpf_fixture' )
			->postTypes( 'wpf_item' )
			->taxonomies( 'wpf_item_cat' )
			->deleteDataOnUninstall()
			->lifecycle()
			->uninstall();

		global $wpdb;
		self::assertSame( '0', $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'wpf_item'" ) );
		self::assertSame( '0', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d", $ids[0] ) ) );
		self::assertSame( '0', $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'wpf_item_cat'" ) );
		self::assertSame( '0', $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE term_id = %d", $termId ) ) );
		self::assertNotNull( get_post( $other ), 'Other posts are untouched.' );
		self::assertFalse( taxonomy_exists( 'wpf_item_cat' ), 'The temporary registration is removed.' );
	}

	public function test_requested_rewrite_flush_happens_on_init(): void {
		$plugin = Plugin::create( self::FILE, 'wpf_fixture' );
		update_option( 'rewrite_rules', array( 'stale' => 'index.php' ) );

		$plugin->requestRewriteFlush();
		$plugin->lifecycle()->maybeFlushRewriteRules();

		self::assertFalse( get_option( 'wpf_fixture_flush_rewrite_rules' ) );
		self::assertArrayNotHasKey( 'stale', (array) get_option( 'rewrite_rules' ) );
	}
}

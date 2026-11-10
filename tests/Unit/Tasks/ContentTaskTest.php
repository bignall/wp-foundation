<?php
/**
 * Content cleanup tests.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Tests\Unit\Tasks;

use Bignall\WPFoundation\Tasks\ContentTask;
use Bignall\WPFoundation\Tasks\TablesTask;
use Bignall\WPFoundation\Tests\Unit\RecordingTask;
use Bignall\WPFoundation\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

final class ContentTaskTest extends TestCase {

	/** @var array<int, string> Post id => type. */
	private array $posts = array();

	/** @var list<array<string, mixed>> */
	private array $queries = array();

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_post_stati' )->justReturn(
			array(
				'publish'    => 'publish',
				'draft'      => 'draft',
				'auto-draft' => 'auto-draft',
				'trash'      => 'trash',
			)
		);
		Functions\when( 'get_posts' )->alias(
			function ( array $args ): array {
				$this->queries[] = $args;
				$ids             = array_keys( array_filter( $this->posts, static fn ( string $type ): bool => in_array( $type, $args['post_type'], true ) ) );
				$ids             = array_values( array_diff( $ids, $args['post__not_in'] ) );
				return array_slice( $ids, 0, $args['numberposts'] );
			}
		);
		Functions\when( 'wp_delete_post' )->alias(
			function ( int $id ) {
				unset( $this->posts[ $id ] );
				return (object) array( 'ID' => $id );
			}
		);
	}

	public function test_deletes_posts_in_every_status_and_batch(): void {
		$this->posts = array_fill_keys( range( 1, 250 ), 'acme_booking' ) + array( 999 => 'post' );

		$task = new ContentTask();
		$task->postTypes( 'acme_booking' );
		$task->uninstall( $this->plugin() );

		self::assertSame( array( 999 => 'post' ), $this->posts );
		self::assertCount( 3, $this->queries );
		self::assertSame( array( 'publish', 'draft', 'auto-draft', 'trash' ), $this->queries[0]['post_status'] );
	}

	public function test_undeletable_posts_do_not_loop_forever(): void {
		$this->posts = array_fill_keys( range( 1, 150 ), 'acme_booking' );
		Functions\when( 'wp_delete_post' )->justReturn( false );

		$task = new ContentTask();
		$task->postTypes( 'acme_booking' );
		$task->uninstall( $this->plugin() );

		// 100 failures, then the remaining 50 with the failures excluded, then stop.
		self::assertCount( 2, $this->queries );
		self::assertCount( 100, $this->queries[1]['post__not_in'] );
	}

	public function test_registers_unregistered_taxonomy_while_deleting_terms(): void {
		$registered = array();
		Functions\when( 'taxonomy_exists' )->justReturn( false );
		Functions\when( 'register_taxonomy' )->alias(
			function ( string $taxonomy ) use ( &$registered ): void {
				$registered[] = "register:{$taxonomy}";
			}
		);
		Functions\when( 'unregister_taxonomy' )->alias(
			function ( string $taxonomy ) use ( &$registered ): void {
				$registered[] = "unregister:{$taxonomy}";
			}
		);
		Functions\expect( 'get_terms' )->once()->with( \Mockery::on( static fn ( array $a ): bool => 'acme_cat' === $a['taxonomy'] && false === $a['hide_empty'] ) )->andReturn( array( 3, 4 ) );
		Functions\expect( 'wp_delete_term' )->twice();

		$task = new ContentTask();
		$task->taxonomies( 'acme_cat' );
		$task->uninstall( $this->plugin() );

		self::assertSame( array( 'register:acme_cat', 'unregister:acme_cat' ), $registered );
	}

	public function test_deletes_cached_term_hierarchy(): void {
		Functions\when( 'taxonomy_exists' )->justReturn( true );
		Functions\when( 'get_terms' )->justReturn( array( 3 ) );
		Functions\when( 'wp_delete_term' )->justReturn( true );
		$this->options['acme_cat_children'] = array( 3 => array( 4 ) );
		$this->options['other_children']    = array( 1 => array( 2 ) );

		$task = new ContentTask();
		$task->taxonomies( 'acme_cat' );
		$task->uninstall( $this->plugin() );

		self::assertArrayNotHasKey( 'acme_cat_children', $this->options );
		self::assertArrayHasKey( 'other_children', $this->options );
	}

	public function test_leaves_registered_taxonomy_registered(): void {
		Functions\when( 'taxonomy_exists' )->justReturn( true );
		Functions\expect( 'register_taxonomy' )->never();
		Functions\expect( 'unregister_taxonomy' )->never();
		Functions\when( 'get_terms' )->justReturn( array() );

		$task = new ContentTask();
		$task->taxonomies( 'acme_cat' );
		$task->uninstall( $this->plugin() );
	}

	public function test_activation_and_deactivation_touch_nothing(): void {
		Functions\expect( 'get_posts' )->never();
		$task = new ContentTask();
		$task->postTypes( 'acme_booking' );
		$task->activate( $this->plugin() );
		$task->upgrade( $this->plugin(), '1.0.0', '2.0.0' );
		$task->deactivate( $this->plugin() );
	}

	public function test_content_runs_before_tables_are_dropped_on_uninstall(): void {
		$plugin = $this->plugin()
			->postTypes( 'acme_booking' )
			->task( new RecordingTask() )
			->tables()
			->taxonomies( 'acme_cat' );

		$classes = array_map( static fn ( $t ): string => $t::class, $plugin->tasks() );

		self::assertSame( ContentTask::class, end( $classes ), 'Content is last, so uninstall (reversed) runs it first.' );
		self::assertSame( 1, count( array_keys( $classes, ContentTask::class, true ) ) );
		self::assertContains( TablesTask::class, $classes );
	}
}

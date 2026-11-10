<?php
/**
 * Content cleanup.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Tasks;

use Bignall\WPFoundation\Plugin;

/**
 * Deletes a plugin's posts and terms on uninstall, when data deletion is enabled.
 *
 * Posts are deleted with `wp_delete_post()`, which also removes their meta, comments and term
 * relationships, in every status, including `auto-draft` and `trash`, which `'any'` skips.
 * Terms are deleted with `wp_delete_term()`, which removes their meta. The plugin's taxonomies
 * aren't registered while WordPress uninstalls it, so each one is registered for the duration
 * of the cleanup. The `{taxonomy}_children` option, where WordPress caches the hierarchy of
 * hierarchical taxonomies, is deleted too.
 *
 * `Plugin::tasks()` keeps this task last, so on uninstall (which runs tasks in reverse) content
 * is deleted before custom tables are dropped.
 */
final class ContentTask extends AbstractTask {

	/**
	 * Posts deleted per query.
	 */
	private const BATCH = 100;

	/**
	 * Post types.
	 *
	 * @var list<string>
	 */
	private array $postTypes = array();

	/**
	 * Taxonomies.
	 *
	 * @var list<string>
	 */
	private array $taxonomies = array();

	/**
	 * Add post types.
	 *
	 * @param string ...$postTypes Post type names.
	 */
	public function postTypes( string ...$postTypes ): void {
		$this->postTypes = array_values( array_unique( array_merge( $this->postTypes, $postTypes ) ) );
	}

	/**
	 * Add taxonomies.
	 *
	 * @param string ...$taxonomies Taxonomy names.
	 */
	public function taxonomies( string ...$taxonomies ): void {
		$this->taxonomies = array_values( array_unique( array_merge( $this->taxonomies, $taxonomies ) ) );
	}

	/**
	 * Activation has nothing to do, so upgrades don't either.
	 *
	 * @param Plugin $plugin The plugin.
	 * @param string $from   Previous version.
	 * @param string $to     New version.
	 */
	public function upgrade( Plugin $plugin, string $from, string $to ): void {}

	/**
	 * {@inheritDoc}
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function uninstall( Plugin $plugin ): void {
		if ( array() !== $this->postTypes ) {
			$this->deletePosts();
		}
		foreach ( $this->taxonomies as $taxonomy ) {
			$this->deleteTerms( $taxonomy );
		}
	}

	/**
	 * Delete every post of the configured types, in batches.
	 */
	private function deletePosts(): void {
		// Every registered status: 'any' leaves out auto-draft and trash.
		$statuses = array_keys( get_post_stati() );
		$failed   = array();

		do {
			$ids = get_posts(
				array(
					'post_type'        => $this->postTypes,
					'post_status'      => $statuses,
					'numberposts'      => self::BATCH,
					'fields'           => 'ids',
					'orderby'          => 'ID',
					'order'            => 'ASC',
					'post__not_in'     => $failed,
					'suppress_filters' => true,
					'no_found_rows'    => true,
				)
			);

			foreach ( $ids as $id ) {
				if ( ! wp_delete_post( (int) $id, true ) ) {
					// Skip posts that can't be deleted, rather than loop forever.
					$failed[] = (int) $id;
				}
			}
			$fullBatch = count( $ids ) === self::BATCH;
		} while ( $fullBatch );
	}

	/**
	 * Delete every term in a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy name.
	 */
	private function deleteTerms( string $taxonomy ): void {
		$registered = false;
		if ( ! taxonomy_exists( $taxonomy ) ) {
			// Uninstall runs without the plugin's init code, so the taxonomy isn't registered.
			register_taxonomy( $taxonomy, array(), array( 'public' => false ) );
			$registered = true;
		}

		$ids = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		);

		if ( is_array( $ids ) ) {
			foreach ( $ids as $id ) {
				wp_delete_term( (int) $id, $taxonomy );
			}
		}

		if ( $registered ) {
			unregister_taxonomy( $taxonomy );
		}

		// Core caches the term hierarchy here (see _get_term_hierarchy()), and deleting terms
		// rebuilds it rather than removing it. Deleted last, so nothing recreates it.
		delete_option( "{$taxonomy}_children" );
	}
}

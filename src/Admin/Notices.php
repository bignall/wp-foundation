<?php
/**
 * Flash admin notices.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Admin;

use Bignall\WPFoundation\Plugin;

/**
 * Admin notices that survive a redirect (post/redirect/get).
 *
 * Flash a notice while handling a form, redirect, and it is shown once on the next admin
 * page the same user loads:
 *
 *     $plugin->notices()->flash( 'success', __( 'Settings saved.', 'acme' ) );
 *     wp_safe_redirect( $url );
 *     exit;
 *
 * Items are plain text by default. To link to something, pass an item as
 * `array( 'text' => ..., 'url' => ... )`: it is printed as a link, with the text escaped
 * with `esc_html()` and the URL with `esc_url()`, so no markup can be injected either way.
 *
 * Notices are stored per user in a transient named `{prefix}_notices_{user id}` and expire
 * after ten minutes if never shown. Each plugin has its own store, so plugins built on the
 * framework never print each other's notices.
 */
final class Notices {

	/**
	 * Supported types, matching WordPress's `notice-{type}` classes.
	 */
	public const TYPES = array( 'success', 'error', 'warning', 'info' );

	private const LIFETIME = 600;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin The plugin.
	 */
	public function __construct( private Plugin $plugin ) {}

	/**
	 * Print pending notices on admin screens.
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'print' ) );
		add_action( 'network_admin_notices', array( $this, 'print' ) );
		add_action( 'user_admin_notices', array( $this, 'print' ) );
	}

	/**
	 * Queue a notice for the current user's next admin page.
	 *
	 * Each item is plain text, or `array( 'text' => ..., 'url' => ... )` to print it as a link.
	 *
	 * @param string                                               $type    One of success, error, warning or info.
	 * @param string                                               $message Message (plain text; it is escaped when printed).
	 * @param array<int, string|array{text: string, url?: string}> $items   Optional details, printed as a list.
	 * @return bool Whether the notice was stored (false when nobody is logged in).
	 */
	public function flash( string $type, string $message, array $items = array() ): bool {
		$key = $this->key();
		if ( null === $key ) {
			return false;
		}

		$notices   = $this->pending();
		$notices[] = array(
			'type'    => in_array( $type, self::TYPES, true ) ? $type : 'info',
			'message' => $message,
			'items'   => array_values( array_map( array( self::class, 'normalizeItem' ), $items ) ),
		);

		return set_transient( $key, $notices, self::LIFETIME );
	}

	/**
	 * Notices waiting for the current user.
	 *
	 * @return list<array{type: string, message: string, items: list<string|array{text: string, url?: string}>}>
	 */
	public function pending(): array {
		$key = $this->key();
		if ( null === $key ) {
			return array();
		}

		$notices = get_transient( $key );
		return is_array( $notices ) ? array_values( $notices ) : array();
	}

	/**
	 * Print and forget pending notices.
	 *
	 * @internal Hooked to `admin_notices`.
	 */
	public function print(): void {
		$notices = $this->pending();
		if ( array() === $notices ) {
			return;
		}

		delete_transient( (string) $this->key() );

		foreach ( $notices as $notice ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p>', esc_attr( $notice['type'] ), esc_html( $notice['message'] ) );
			if ( array() !== $notice['items'] ) {
				echo '<ul class="ul-disc">';
				foreach ( $notice['items'] as $item ) {
					echo '<li>' . self::itemHtml( $item ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in itemHtml().
				}
				echo '</ul>';
			}
			echo '</div>';
		}
	}

	/**
	 * Normalize an item to plain text or a `text`/`url` pair.
	 *
	 * @param mixed $item Item as passed to flash().
	 * @return string|array{text: string, url?: string}
	 */
	private static function normalizeItem( mixed $item ): string|array {
		if ( ! is_array( $item ) ) {
			return is_scalar( $item ) ? (string) $item : '';
		}

		$text = isset( $item['text'] ) && is_scalar( $item['text'] ) ? (string) $item['text'] : '';
		$url  = isset( $item['url'] ) && is_scalar( $item['url'] ) ? (string) $item['url'] : '';

		return '' === $url ? array( 'text' => $text ) : array(
			'text' => $text,
			'url'  => $url,
		);
	}

	/**
	 * Escaped HTML for an item: text, or a link when it has a URL with an allowed protocol.
	 *
	 * @param mixed $item Stored item.
	 */
	private static function itemHtml( mixed $item ): string {
		$item = self::normalizeItem( $item );
		if ( is_string( $item ) ) {
			return esc_html( $item );
		}

		$text = esc_html( $item['text'] );
		$url  = isset( $item['url'] ) ? esc_url( $item['url'] ) : '';

		// esc_url() returns '' for disallowed protocols such as javascript:, so fall back to text.
		return '' === $url ? $text : sprintf( '<a href="%s">%s</a>', $url, $text );
	}

	/**
	 * Transient name for the current user, or null when nobody is logged in.
	 */
	private function key(): ?string {
		$userId = get_current_user_id();
		return $userId > 0 ? $this->plugin->optionName( "notices_{$userId}" ) : null;
	}
}

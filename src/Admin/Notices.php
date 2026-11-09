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
	 * @param string       $type    One of success, error, warning or info.
	 * @param string       $message Message (plain text; it is escaped when printed).
	 * @param list<string> $items   Optional details, printed as a list.
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
			'items'   => array_map( 'strval', $items ),
		);

		return set_transient( $key, $notices, self::LIFETIME );
	}

	/**
	 * Notices waiting for the current user.
	 *
	 * @return list<array{type: string, message: string, items: list<string>}>
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
					echo '<li>' . esc_html( $item ) . '</li>';
				}
				echo '</ul>';
			}
			echo '</div>';
		}
	}

	/**
	 * Transient name for the current user, or null when nobody is logged in.
	 */
	private function key(): ?string {
		$userId = get_current_user_id();
		return $userId > 0 ? $this->plugin->optionName( "notices_{$userId}" ) : null;
	}
}

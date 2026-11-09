<?php
/**
 * Deactivation feedback.
 *
 * @package Bignall\WPFoundation
 */

declare(strict_types=1);

namespace Bignall\WPFoundation\Feedback;

/**
 * A submitted survey response. Contains no personal data unless the user agreed to share
 * their site URL.
 */
final class Feedback {

	/**
	 * Constructor.
	 *
	 * @param string      $reason        Reason id.
	 * @param string      $details       Free-text details, possibly empty.
	 * @param string      $plugin        Plugin slug.
	 * @param string      $pluginVersion Plugin version.
	 * @param string      $wpVersion     WordPress version.
	 * @param string      $phpVersion    PHP version.
	 * @param string      $locale        Site locale.
	 * @param bool        $multisite     Whether the site is part of a network.
	 * @param int         $submittedAt   Unix timestamp.
	 * @param string|null $siteUrl       Site URL, only when the user consented.
	 */
	public function __construct(
		public readonly string $reason,
		public readonly string $details,
		public readonly string $plugin,
		public readonly string $pluginVersion,
		public readonly string $wpVersion,
		public readonly string $phpVersion,
		public readonly string $locale,
		public readonly bool $multisite,
		public readonly int $submittedAt,
		public readonly ?string $siteUrl = null,
	) {}

	/**
	 * Array form, for storage or sending.
	 *
	 * @return array<string, string|int|bool|null>
	 */
	public function toArray(): array {
		return array(
			'reason'         => $this->reason,
			'details'        => $this->details,
			'plugin'         => $this->plugin,
			'plugin_version' => $this->pluginVersion,
			'wp_version'     => $this->wpVersion,
			'php_version'    => $this->phpVersion,
			'locale'         => $this->locale,
			'multisite'      => $this->multisite,
			'submitted_at'   => $this->submittedAt,
			'site_url'       => $this->siteUrl,
		);
	}
}

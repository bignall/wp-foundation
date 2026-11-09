<?php
/**
 * Deactivation reason.
 *
 * @package Arbe\WPFoundation
 */

declare(strict_types=1);

namespace Arbe\WPFoundation\Feedback;

/**
 * One answer to "why are you deactivating?".
 */
final class Reason {

	/**
	 * Constructor.
	 *
	 * @param string      $id            Stable identifier sent with the feedback, e.g. `found_better`.
	 * @param string      $label         Text shown to the user.
	 * @param string|null $detailsPrompt Placeholder for an optional details field; null hides the field.
	 */
	public function __construct(
		public readonly string $id,
		public readonly string $label,
		public readonly ?string $detailsPrompt = null,
	) {}

	/**
	 * Data passed to the survey script.
	 *
	 * @return array{id: string, label: string, detailsPrompt: string|null}
	 */
	public function toArray(): array {
		return array(
			'id'            => $this->id,
			'label'         => $this->label,
			'detailsPrompt' => $this->detailsPrompt,
		);
	}
}

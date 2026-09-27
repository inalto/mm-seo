<?php
/**
 * Check: Internal Links.
 *
 * @package MMSEO\Analysis\Checks
 */

namespace MMSEO\Analysis\Checks;

use MMSEO\Analysis\Check;

defined( 'ABSPATH' ) || exit;

/**
 * Checks that the content contains at least one internal link.
 *
 * ID: internal_links
 */
class InternalLinks extends Check {

	/**
	 * {@inheritdoc}
	 */
	public function id(): string {
		return 'internal_links';
	}

	/**
	 * {@inheritdoc}
	 */
	public function weight(): int {
		return 2;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param array<string, mixed> $context
	 * @return array{status: string, score: int, text: string}
	 */
	public function run( array $context ): array {
		$content = $context['content'] ?? [];
		$links   = $content['links'] ?? [];

		$internal_count = 0;
		foreach ( $links as $link ) {
			if ( ! empty( $link['internal'] ) ) {
				$internal_count++;
			}
		}

		if ( $internal_count >= 1 ) {
			return [
				'status' => self::STATUS_GOOD,
				'score'  => 9,
				/* translators: %d: number of internal links */
				'text'   => sprintf(
					_n(
						'%d internal link found — good.',
						'%d internal links found — good.',
						$internal_count,
						'mm-seo'
					),
					$internal_count
				),
			];
		}

		return [
			'status' => self::STATUS_BAD,
			'score'  => 3,
			'text'   => __( 'No internal links found. Add links to other pages on your site.', 'mm-seo' ),
		];
	}
}

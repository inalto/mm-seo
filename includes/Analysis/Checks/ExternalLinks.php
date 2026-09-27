<?php
/**
 * Check: External Links.
 *
 * @package MMSEO\Analysis\Checks
 */

namespace MMSEO\Analysis\Checks;

use MMSEO\Analysis\Check;

defined( 'ABSPATH' ) || exit;

/**
 * Checks that the content contains at least one external (outbound) link.
 * Low-weight check: weight 1.
 *
 * ID: external_links
 */
class ExternalLinks extends Check {

	/**
	 * {@inheritdoc}
	 */
	public function id(): string {
		return 'external_links';
	}

	/**
	 * {@inheritdoc}
	 * Low weight — external links are beneficial but not critical.
	 */
	public function weight(): int {
		return 1;
	}

	/**
	 * {@inheritdoc}
	 *
	 * @param array<string, mixed> $context
	 * @return array{status: string, score: int, text: string}
	 */
	public function run( array $context ): array {
		$content        = $context['content'] ?? [];
		$links          = $content['links'] ?? [];
		$external_count = 0;

		foreach ( $links as $link ) {
			if ( isset( $link['internal'] ) && false === $link['internal'] ) {
				$external_count++;
			}
		}

		if ( $external_count >= 1 ) {
			return [
				'status' => self::STATUS_GOOD,
				'score'  => 9,
				/* translators: %d: number of external links */
				'text'   => sprintf(
					_n(
						'%d external link found — good.',
						'%d external links found — good.',
						$external_count,
						'mm-seo'
					),
					$external_count
				),
			];
		}

		return [
			'status' => self::STATUS_OK,
			'score'  => 5,
			'text'   => __( 'No external links found. Consider linking to authoritative sources.', 'mm-seo' ),
		];
	}
}

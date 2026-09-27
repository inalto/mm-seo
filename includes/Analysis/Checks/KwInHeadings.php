<?php
/**
 * Check: Keyword in Headings.
 *
 * @package MMSEO\Analysis\Checks
 */

namespace MMSEO\Analysis\Checks;

use MMSEO\Analysis\Check;
use MMSEO\ContentExtractor;

defined( 'ABSPATH' ) || exit;

/**
 * Verifies that the focus keyword appears in at least one subheading (H2–H6).
 *
 * ID: kw_in_headings
 */
class KwInHeadings extends Check {

	/**
	 * {@inheritdoc}
	 */
	public function id(): string {
		return 'kw_in_headings';
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
		$kw      = (string) ( $context['focus_kw'] ?? '' );
		$content = $context['content'] ?? [];
		$headings = $content['headings'] ?? [];

		if ( '' === $kw ) {
			return [
				'status' => self::STATUS_OK,
				'score'  => 5,
				'text'   => __( 'Set a focus keyword to get keyword checks.', 'mm-seo' ),
			];
		}

		foreach ( $headings as $heading ) {
			if ( ContentExtractor::keyword_match( (string) $heading, $kw ) ) {
				return [
					'status' => self::STATUS_GOOD,
					'score'  => 9,
					'text'   => __( 'The focus keyword appears in a subheading.', 'mm-seo' ),
				];
			}
		}

		return [
			'status' => self::STATUS_OK,
			'score'  => 5,
			'text'   => __( 'The focus keyword does not appear in any subheading. Consider adding it to at least one H2 or H3.', 'mm-seo' ),
		];
	}
}
